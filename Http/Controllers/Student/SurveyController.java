package com.app.controllers.student;

import com.app.controllers.Controller;
import com.app.lib.HelperTrait;
import org.springframework.web.bind.annotation.*;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;

import com.app.models.*;
import com.app.repositories.*;
import com.app.services.*;

import java.util.HashMap;
import java.util.Map;

@RestController
@RequestMapping("/student/survey")
public class SurveyController extends Controller {

    private final SurveyRepository surveyRepository;
    private final SurveyResponseRepository surveyResponseRepository;
    private final StudentService studentService;

    public SurveyController(SurveyRepository surveyRepository, 
                            SurveyResponseRepository surveyResponseRepository,
                            StudentService studentService) {
        this.surveyRepository = surveyRepository;
        this.surveyResponseRepository = surveyResponseRepository;
        this.studentService = studentService;
    }

    private String getLayout() {
        Authentication auth = SecurityContextHolder.getContext().getAuthentication();
        if (auth != null && auth.isAuthenticated() && auth.getAuthorities().stream()
                .anyMatch(a -> a.getAuthority().equals("ROLE_STUDENT"))) {
            return "layouts/student";
        } else {
            return "layouts/survey";
        }
    }

    @GetMapping("/{hash}")
    public ResponseEntity<?> survey(@PathVariable String hash) {
        Map<String, Object> output = new HashMap<>();
        Survey survey = surveyRepository.findByHashAndEnabled(hash.trim(), true);

        if (survey == null) {
            return ResponseEntity.badRequest().body("Invalid survey");
        }

        if (survey.isPrivate()) {
            if (!studentService.isLoggedIn()) {
                return ResponseEntity.status(302).header("Location", "/login").build();
            }

            if (!studentService.isEnrolledInSurveySessions(survey.getId())) {
                return ResponseEntity.badRequest().body("No survey permission");
            }
        }

        output.put("pageTitle", "Survey: " + survey.getName());
        output.put("survey", survey);
        output.put("loggedIn", studentService.isLoggedIn());
        output.put("totalQuestions", survey.getSurveyQuestions().size());
        output.put("layout", getLayout());

        return ResponseEntity.ok(output);
    }

    @PostMapping("/{hash}")
    public ResponseEntity<?> submitSurvey(@PathVariable String hash, @RequestBody Map<String, Object> data) {
        Survey survey = surveyRepository.findByHashAndEnabled(hash.trim(), true);

        if (survey == null) {
            return ResponseEntity.badRequest().body("Invalid survey");
        }

        SurveyResponse surveyResponse = new SurveyResponse();
        surveyResponse.setSurvey(survey);

        if (studentService.isLoggedIn()) {
            surveyResponse.setStudentId(studentService.getCurrentStudentId());
        }

        surveyResponse = surveyResponseRepository.save(surveyResponse);

        for (Map.Entry<String, Object> entry : data.entrySet()) {
            if (entry.getKey().startsWith("question_")) {
                surveyResponse.getSurveyOptions().add(Long.parseLong(entry.getValue().toString()));
            }
        }

        surveyResponseRepository.save(surveyResponse);

        return ResponseEntity.status(302).header("Location", "/student/survey/complete").build();
    }

    @GetMapping("/complete")
    public ResponseEntity<?> complete() {
        Map<String, Object> output = new HashMap<>();
        output.put("pageTitle", "Survey Submitted");
        output.put("layout", getLayout());
        return ResponseEntity.ok(output);
    }

    private ResponseEntity<?> showMessage(String message) {
        Map<String, Object> output = new HashMap<>();
        output.put("message", message);
        output.put("pageTitle", "Survey");
        return ResponseEntity.ok(output);
    }
}

