package com.example.demo.controllers;

import com.example.demo.models.Template;
import com.example.demo.services.TemplateService;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.core.io.Resource;
import org.springframework.core.io.ResourceLoader;
import org.springframework.http.HttpHeaders;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.List;

@RestController
@RequestMapping("/api/utils")
public class UtilsController {

    @Autowired
    private TemplateService templateService;

    @Autowired
    private ResourceLoader resourceLoader;

    @GetMapping("/css")
    public ResponseEntity<String> css(@RequestParam String path) {
        String cssFilePath = path + ".css";
        // Get current template
        Template template = templateService.getCurrentTemplate();

        List<Template.Color> colors = template.getTemplateColors();

        // Construct the file path (update this according to your directory structure)
        Path filePath = Paths.get("path/to/css/directory", cssFilePath);

        if (!Files.exists(filePath)) {
            return ResponseEntity.status(HttpStatus.NOT_FOUND).body("Invalid file");
        }

        String content;
        try {
            content = Files.readString(filePath);
        } catch (IOException e) {
            return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR).body("Error reading file");
        }

        // Replace colors in the CSS content
        for (Template.Color color : colors) {
            if (color.getUserColor() != null && !color.getUserColor().isEmpty()) {
                content = content.replaceAll(color.getOriginalColor(), color.getUserColor());
            }
        }

        HttpHeaders headers = new HttpHeaders();
        headers.add("Content-Type", "text/css");
        return new ResponseEntity<>(content, headers, HttpStatus.OK);
    }
}
