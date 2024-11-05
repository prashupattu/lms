package com.example.controllers.admin;

import com.example.controllers.Controller;
import com.example.lib.HelperTrait;
import com.example.models.Template;
import org.springframework.http.ResponseEntity;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.multipart.MultipartFile;

import javax.validation.Valid;
import java.io.File;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.*;

@Controller
@RequestMapping("/admin/templates")
public class TemplatesController extends Controller {

    private static final String TEMPLATE_PATH = "path/to/templates";
    private static final String UPLOAD_PATH = "path/to/uploads";

    private HelperTrait helperTrait;

    @GetMapping
    public String index(Model model) {
        List<String> templates = getDirectoryContents(TEMPLATE_PATH);
        Template currentTemplate = Template.findByEnabled(true);
        
        model.addAttribute("templates", templates);
        model.addAttribute("currentTemplate", currentTemplate);
        return "admin/templates/index";
    }

    @PostMapping("/install/{templateDir}")
    public String install(@PathVariable String templateDir) {
        Template template = Template.findByDirectory(templateDir);
        if (template == null) {
            Template.updateAllExcept(templateDir, false);
            Map<String, String> info = templateInfo(templateDir);
            
            template = new Template();
            template.setName(info.get("name"));
            template.setEnabled(true);
            template.setDirectory(templateDir);
            template.save();
        } else {
            Template.updateAllExcept(templateDir, false);
            template.setEnabled(true);
            template.save();
        }
        
        return "redirect:/admin/templates";
    }

    @GetMapping("/settings")
    public String settings(Model model) {
        Template template = getCurrentTemplate();
        if (template == null) {
            return "redirect:/admin/templates";
        }

        String optionPath = TEMPLATE_PATH + "/" + template.getDirectory() + "/options";
        List<String> options = getDirectoryContents(optionPath);

        TreeMap<Integer, String> optionSort = new TreeMap<>();
        for (String option : options) {
            Map<String, Object> info = includePhpFile(optionPath + "/" + option + "/info.php");
            optionSort.put((Integer) info.get("position"), option);
        }

        Map<String, Map<String, Object>> settings = new LinkedHashMap<>();
        for (String option : optionSort.values()) {
            Map<String, Object> data = new HashMap<>();
            Map<String, Object> info = includePhpFile(optionPath + "/" + option + "/info.php");
            data.put("name", translate("temp_" + template.getDirectory() + "." + info.get("name")));
            data.put("description", translate("temp_" + template.getDirectory() + "." + info.get("description")));
            data.put("form", template.getDirectory() + ".options." + option + ".form");

            TemplateOption optionRow = template.getTemplateOptions().stream()
                    .filter(o -> o.getName().equals(option))
                    .findFirst()
                    .orElse(null);

            if (optionRow != null) {
                data.put("values", unserialize(optionRow.getValue()));
                data.put("enabled", optionRow.isEnabled());
            } else {
                data.put("values", new HashMap<>());
                data.put("enabled", false);
            }

            settings.put(option, data);
        }

        model.addAttribute("settings", settings);
        model.addAttribute("template", template);
        return "admin/templates/settings";
    }

    @PostMapping("/saveOptions/{option}")
    @ResponseBody
    public ResponseEntity<?> saveOptions(@PathVariable String option, @RequestBody Map<String, Object> data) {
        Template template = getCurrentTemplate();

        TemplateOption optionRow = template.getTemplateOptions().stream()
                .filter(o -> o.getName().equals(option))
                .findFirst()
                .orElse(null);

        if (optionRow == null) {
            optionRow = new TemplateOption();
            optionRow.setName(option);
            optionRow.setTemplate(template);
            template.getTemplateOptions().add(optionRow);
        }

        optionRow.setValue(serialize(data));
        optionRow.setEnabled((Boolean) data.get("enabled"));
        optionRow.save();

        return ResponseEntity.ok().body(Collections.singletonMap("status", true));
    }

    @PostMapping("/upload")
    @ResponseBody
    public ResponseEntity<?> upload(@RequestParam("image") MultipartFile file) {
        if (file.isEmpty()) {
            return ResponseEntity.badRequest().body(Collections.singletonMap("error", "No file uploaded"));
        }

        try {
            String fileName = file.getOriginalFilename();
            String filePath = UPLOAD_PATH + "/" + TEMPLATE_FILES;
            Path path = Paths.get(filePath);
            if (!Files.exists(path)) {
                Files.createDirectories(path);
            }
            Files.copy(file.getInputStream(), path.resolve(fileName));

            Map<String, Object> response = new HashMap<>();
            response.put("file_path", "/uploads/" + TEMPLATE_FILES + "/" + fileName);
            response.put("file_name", fileName);
            response.put("status", true);
            return ResponseEntity.ok(response);
        } catch (IOException e) {
            return ResponseEntity.badRequest().body(Collections.singletonMap("error", "Failed to upload file"));
        }
    }

    @GetMapping("/colors")
    public String colors(Model model) {
        Template template = getCurrentTemplate();
        if (template == null) {
            return "redirect:/admin/templates";
        }

        String colorFile = TEMPLATE_PATH + "/" + template.getDirectory() + "/colors.php";
        if (!new File(colorFile).exists()) {
            return "redirect:/admin/templates";
        }

        List<String> colorList = includePhpFile(colorFile);
        model.addAttribute("colorList", colorList);
        model.addAttribute("template", template);
        return "admin/templates/colors";
    }

    @PostMapping("/saveColors")
    public String saveColors(@RequestBody Map<String, String> colors) {
        Template template = getCurrentTemplate();
        if (template == null) {
            return "redirect:/admin/templates";
        }

        String colorFile = TEMPLATE_PATH + "/" + template.getDirectory() + "/colors.php";
        if (!new File(colorFile).exists()) {
            return "redirect:/admin/templates";
        }

        List<String> colorList = includePhpFile(colorFile);

        for (String color : colorList) {
            TemplateColor templateColor = template.getTemplateColors().stream()
                    .filter(c -> c.getOriginalColor().equals(color))
                    .findFirst()
                    .orElse(null);

            if (templateColor == null) {
                templateColor = new TemplateColor();
                templateColor.setOriginalColor(color);
                templateColor.setTemplate(template);
                template.getTemplateColors().add(templateColor);
            }

            templateColor.setUserColor(colors.get(color + "_new"));
            templateColor.save();
        }

        return "redirect:/admin/templates/colors";
    }

    private Template getCurrentTemplate() {
        return Template.findByEnabled(true);
    }

    // Helper methods (to be implemented)
    private List<String> getDirectoryContents(String path) {
        // Implementation needed
        return new ArrayList<>();
    }

    private Map<String, String> templateInfo(String templateDir) {
        // Implementation needed
        return new HashMap<>();
    }

    private Map<String, Object> includePhpFile(String filePath) {
        // Implementation needed
        return new HashMap<>();
    }

    private String translate(String key) {
        // Implementation needed
        return key;
    }

    private Map<String, Object> unserialize(String value) {
        // Implementation needed
        return new HashMap<>();
    }

    private String serialize(Map<String, Object> data) {
        // Implementation needed
        return "";
    }
}

