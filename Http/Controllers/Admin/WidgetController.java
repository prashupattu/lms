package com.app.controllers.admin;

import com.app.controllers.Controller;
import com.app.lib.HelperTrait;
import com.app.v2.model.SessionTable;
import com.app.v2.model.WidgetTable;
import com.app.v2.model.WidgetValueTable;
import org.springframework.web.bind.annotation.*;
import org.springframework.ui.Model;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;
import org.jsoup.Jsoup;
import org.jsoup.nodes.Document;
import org.jsoup.select.Elements;

import java.util.*;

@RestController
@RequestMapping("/admin/widget")
public class WidgetController extends Controller {

    private HelperTrait helperTrait;
    private Map<String, Object> data = new HashMap<>();

    @GetMapping
    public String index(Model model) {
        data.put("pageTitle", __lang("Homepage Widgets"));

        WidgetTable widgetsTable = new WidgetTable();
        WidgetValueTable widgetValueTable = new WidgetValueTable();

        List<Widget> widgets = widgetsTable.getRecords();
        data.put("widgets", widgets);
        List<String> editors = new ArrayList<>();
        Map<Integer, Map<String, Object>> html = new HashMap<>();

        // Create the elements for creating a new widget
        Map<String, String> options = new LinkedHashMap<>();
        options.put("", "");
        for (Widget row : widgets) {
            if (!row.getCode().equals("textbtn")) {
                options.put(String.valueOf(row.getId()), __lang(row.getName()));
            }
        }
        data.put("createSelect", options);

        List<WidgetValue> merchantWidgets = widgetValueTable.getWidgets();
        data.put("merchantWidgets", merchantWidgets);

        SessionTable sessionTable = new SessionTable();
        List<Session> sessions = sessionTable.getLimitedRecords(1000);

        StringBuilder sessionSelect = new StringBuilder("<select name=\"session[num]\" class=\"form-control select2\"><option></option>");
        for (Session row : sessions) {
            String type = sessionType(row.getType());
            sessionSelect.append(String.format("<option value=\"%d\">%s (%s)</option>", row.getId(), row.getName(), type));
        }
        sessionSelect.append("</select>");

        for (WidgetValue row : merchantWidgets) {
            String form = getView("admin.widget.forms." + row.getCode());
            String newForm = form.replace("[sessionselect]", sessionSelect.toString());

            int repeat = row.getRepeat();
            if (repeat > 0) {
                StringBuilder repeatedForm = new StringBuilder();
                for (int i = 1; i <= repeat; i++) {
                    repeatedForm.append(newForm.replace("[num]", String.valueOf(i)));
                }
                form = repeatedForm.toString();
            }

            String noImage = getBaseUrl() + "/img/no_image.jpg";
            form = form.replace("[base]", getBaseUrl()).replace("[no_image]", noImage);

            if (row.getValue() != null) {
                Map<String, Object> valueArray = deserialize(row.getValue());
                Document doc = Jsoup.parse(form);

                for (Map.Entry<String, Object> entry : valueArray.entrySet()) {
                    String key = entry.getKey();
                    Object value = entry.getValue();

                    Elements elements = doc.select("[name='" + key + "']");
                    if (!elements.isEmpty()) {
                        elements.val(value.toString());
                    }

                    Elements dataElements = doc.select("[data-name='" + key + "']");
                    if (!dataElements.isEmpty()) {
                        if (value != null && !(value instanceof Map)) {
                            dataElements.attr("src", resizeImage(value.toString(), 100, 100, getBaseUrl()));
                        } else {
                            dataElements.attr("src", noImage);
                        }
                    }
                }

                form = doc.html();
            }

            if (form.contains("rte")) {
                Document doc = Jsoup.parse(form);
                Elements rteElements = doc.select("textarea.rte");
                int count = 1;
                for (org.jsoup.nodes.Element element : rteElements) {
                    String editorId = row.getCode() + "_editor" + count;
                    element.attr("id", editorId);
                    editors.add(editorId);
                    count++;
                }
                form = doc.html();
            }

            Map<String, Object> widgetHtml = new HashMap<>();
            widgetHtml.put("form", form);
            widgetHtml.put("enabled", createSelect("enabled_" + row.getId(), Arrays.asList("Enabled", "Disabled")));
            widgetHtml.put("sortOrder", createNumberInput("sortOrder_" + row.getId(), "Sort Order"));
            widgetHtml.put("description", __lang(row.getName() + "-desc"));
            widgetHtml.put("code", row.getCode());
            widgetHtml.put("name", row.getName());
            widgetHtml.put("visibility", createSelect("visibility", Arrays.asList("Website", "Mobile App", "website-app")));

            html.put(row.getId(), widgetHtml);
        }

        data.put("editors", editors);
        data.put("form", html);

        model.addAllAttributes(data);
        return "admin/widget/index";
    }

    @PostMapping("/create")
    public String create(@RequestBody Map<String, Object> requestData, RedirectAttributes redirectAttributes) {
        WidgetValueTable merchantMobileWidgetTable = new WidgetValueTable();
        requestData.put("enabled", 1);
        requestData.remove("_token");
        merchantMobileWidgetTable.addRecord(requestData);

        redirectAttributes.addFlashAttribute("flash_message", __lang("Widget created!"));
        return "redirect:/admin/widget";
    }

    @PostMapping("/process/{id}")
    @ResponseBody
    public Map<String, Object> process(@PathVariable int id, @RequestBody Map<String, Object> data) {
        WidgetValueTable merchantTemplateOptionsTable = new WidgetValueTable();
        boolean status = false;
        String message = __lang("Submission Failed");

        if (merchantTemplateOptionsTable.saveOptions(id, data)) {
            status = true;
            message = __lang("Changes Saved!");
        }

        Map<String, Object> response = new HashMap<>();
        response.put("status", status);
        response.put("message", message);
        return response;
    }

    @DeleteMapping("/{id}")
    public String delete(@PathVariable int id) {
        WidgetValueTable merchantTemplateOptionsTable = new WidgetValueTable();
        merchantTemplateOptionsTable.deleteRecord(id);
        return "redirect:/admin/widget";
    }

    // Helper methods (to be implemented)
    private String __lang(String key) {
        // Implement language translation
        return key;
    }

    private String getView(String viewName) {
        // Implement view rendering
        return "";
    }

    private String getBaseUrl() {
        // Implement base URL retrieval
        return "";
    }

    private String sessionType(String type) {
        // Implement session type conversion
        return "";
    }

    private String resizeImage(String image, int width, int height, String baseUrl) {
        // Implement image resizing
        return "";
    }

    private Map<String, Object> deserialize(String value) {
        // Implement deserialization
        return new HashMap<>();
    }

    private String createSelect(String name, List<String> options) {
        // Implement select creation
        return "";
    }

    private String createNumberInput(String name, String placeholder) {
        // Implement number input creation
        return "";
    }
}

