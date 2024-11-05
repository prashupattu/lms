package com.example.demo.controllers;

import com.example.demo.models.Setting;
import com.example.demo.models.User;
import com.example.demo.models.Video;
import com.example.demo.services.SettingService;
import com.example.demo.services.UserService;
import com.example.demo.services.VideoService;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.web.bind.annotation.*;

import javax.validation.Valid;
import java.util.HashMap;
import java.util.Map;

@RestController
@RequestMapping("/api")
public class IndexController {

    @Autowired
    private UserService userService;

    @Autowired
    private VideoService videoService;

    @Autowired
    private SettingService settingService;

    private final BCryptPasswordEncoder passwordEncoder = new BCryptPasswordEncoder();

    @PostMapping("/migrate")
    public ResponseEntity<Map<String, Object>> migrate() {
        if (isSaas()) {
            // Call migration logic here (custom implementation needed)
            // For example, you might invoke a migration script or similar functionality
            Map<String, Object> response = new HashMap<>();
            response.put("status", true);
            return ResponseEntity.ok(response);
        } else {
            return ResponseEntity.status(401).build();
        }
    }

    @PostMapping("/videos/{videoId}/update")
    public ResponseEntity<String> updateVideo(@PathVariable Long videoId) {
        Video video = videoService.findById(videoId);
        if (video != null) {
            video.setReady(1);
            videoService.save(video);
            return ResponseEntity.ok("Video updated");
        } else {
            return ResponseEntity.notFound().build();
        }
    }

    @PostMapping("/setup")
    public ResponseEntity<Map<String, Object>> setup(@Valid @RequestBody SetupRequest setupRequest) {
        if (isSetupComplete()) {
            return ResponseEntity.status(401).build();
        }

        User adminUser = userService.findByRoleId(1);
        if (adminUser != null) {
            adminUser.setName(setupRequest.getFirstName());
            adminUser.setLastName(setupRequest.getLastName());
            adminUser.setPassword(passwordEncoder.encode(setupRequest.getPassword()));
            adminUser.setEmail(setupRequest.getEmail());
            userService.save(adminUser);
        }

        // Update site settings
        updateSetting("general_homepage_title", setupRequest.getGeneralSiteName());
        updateSetting("general_site_name", setupRequest.getGeneralSiteName());
        updateSetting("general_admin_email", setupRequest.getGeneralAdminEmail());
        updateSetting("general_tel", setupRequest.getGeneralTel());
        updateSetting("country_id", setupRequest.getCountryId());

        Map<String, Object> response = new HashMap<>();
        response.put("status", true);
        return ResponseEntity.ok(response);
    }

    private void updateSetting(String key, String value) {
        Setting setting = settingService.findByKey(key);
        if (setting != null) {
            setting.setValue(value);
            settingService.save(setting);
        }
    }

    // Check if setup is complete (this method needs to be implemented based on your logic)
    private boolean isSetupComplete() {
        return false; // Replace with actual condition
    }

    // Check if the application is in SaaS mode (this method needs to be implemented based on your logic)
    private boolean isSaas() {
        return true; // Replace with actual condition
    }
}

// DTO for setup request
class SetupRequest {
    private String firstName;
    private String lastName;
    private String email;
    private String password;
    private String generalSiteName;
    private String generalAdminEmail;
    private String generalTel;
    private String countryId;

    // Getters and setters...
}
