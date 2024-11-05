import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

import org.springframework.web.bind.annotation.*;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;

@RestController
@RequestMapping("/student/test")
public class TestController {

    @Autowired
    private TestTable testTable;

    @Autowired
    private TestQuestionTable testQuestionTable;

    @Autowired
    private StudentTestTable studentTestTable;

    @Autowired
    private StudentTestOptionTable studentTestOptionTable;

    @Autowired
    private StudentSessionTable studentSessionTable;

    @Autowired
    private AttendanceTable attendanceTable;

    @Autowired
    private SessionLessonTable sessionLessonTable;

    @Autowired
    private Course course;

    @GetMapping
    public ResponseEntity<Map<String, Object>> index(@RequestParam(required = false) Integer page) {
        Paginator paginator = testTable.getStudentRecords(getId());
        paginator.setCurrentPageNumber(page != null ? page : 1);
        paginator.setItemCountPerPage(30);

        Map<String, Object> response = new HashMap<>();
        response.put("paginator", paginator);
        response.put("pageTitle", "Tests");
        response.put("studentTest", studentTestTable);
        response.put("questionTable", testQuestionTable);
        response.put("id", getId());

        return ResponseEntity.ok(response);
    }

    @GetMapping("/taketest/{id}")
    public ResponseEntity<Map<String, Object>> takeTest(@PathVariable Long id) {
        Map<String, Object> output = new HashMap<>();
        TestRow testRow = testTable.getRecord(id);
        output.put("testRow", testRow);
        output.put("pageTitle", "Take Test: " + testRow.getName());

        if (studentTestTable.hasTest(id, getId()) && !testRow.getAllowMultiple()) {
            flashMessage("Test already taken.");
            return ResponseEntity.status(302).header("Location", "/student/test").build();
        }

        // Additional logic for handling test timing and permissions...

        List<Question> questions = new ArrayList<>();
        List<Option> options = new ArrayList<>();
        for (QuestionRow row : testQuestionTable.getPaginatedRecords(false, id)) {
            questions.add(row);
            options.addAll(testOptionTable.getOptionRecords(row.getId()));
        }

        output.put("questions", questions);
        output.put("options", options);

        return ResponseEntity.ok(output);
    }

    @PostMapping("/processtest/{id}")
    public ResponseEntity<Void> processTest(@PathVariable Long id, @RequestBody Map<String, Object> data) {
        Long studentTestId = (Long) data.get("student_test_id");
        StudentTestRow row = studentTestTable.getRecord(studentTestId);
        validateOwner(row);

        int correct = 0;
        int totalQuestions = testQuestionTable.getPaginatedRecords(false, id).size();

        for (QuestionRow question : testQuestionTable.getPaginatedRecords(false, id)) {
            if (data.containsKey("question_" + question.getId())) {
                Long optionId = (Long) data.get("question_" + question.getId());
                studentTestOptionTable.addRecord(studentTestId, optionId);
                OptionRow optionRow = testOptionTable.getRecord(optionId);
                if (optionRow.getIsCorrect() == 1) {
                    correct++;
                }
            }
        }

        double score = ((double) correct / totalQuestions) * 100;
        studentTestTable.updateScore(studentTestId, score);
        return ResponseEntity.status(302).header("Location", "/student/test/result/" + studentTestId).build();
    }

    @PostMapping("/starttest/{id}")
    public ResponseEntity<Map<String, Object>> startTest(@PathVariable Long id) {
        Long studentTestId = studentTestTable.addRecord(getId(), id, 0);
        Map<String, Object> output = new HashMap<>();
        output.put("id", studentTestId);
        output.put("status", true);
        return ResponseEntity.ok(output);
    }

    @GetMapping("/result/{id}")
    public ResponseEntity<Map<String, Object>> result(@PathVariable Long id) {
        StudentTestRow row = studentTestTable.getRecord(id);
        validateOwner(row);
        TestRow testRow = testTable.getRecord(row.getTestId());

        Map<String, Object> output = new HashMap<>();
        output.put("row", row);
        output.put("pageTitle", "Test Result: " + testRow.getName());
        output.put("testRow", testRow);

        // Additional logic for handling attendance and results...

        return ResponseEntity.ok(output);
    }

    // Additional methods for test results, report card, statement, etc.

    private Long getId() {
        // Logic to get the current student's ID
        return 0L; // Placeholder
    }

    private void flashMessage(String message) {
        // Logic to flash a message to the user
    }

    private void validateOwner(StudentTestRow row) {
        // Logic to validate ownership of the test
    }
}

