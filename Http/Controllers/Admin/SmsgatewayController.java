package com.app.controllers.admin;

import com.app.controllers.Controller;
import com.app.lib.BaseForm;
import com.app.lib.HelperTrait;
import com.app.models.SmsGateway;
import com.app.v2.models.SettingTable;
import com.app.v2.models.SmsGatewayTable;
import org.springframework.web.bind.annotation.*;
import org.springframework.ui.Model;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

import java.util.*;

@RestController
@RequestMapping("/admin/smsgateway")
public class SmsgatewayController extends Controller {

    private HelperTrait helperTrait;

    @GetMapping
    public String index(Model model) {
        List<String> gateways = getDirectoryContents(MESSAGING_PATH);
        SmsGatewayTable table = new SmsGatewayTable();
        SettingTable settingsTable = new SettingTable();
        BaseForm form = getSmsForm();

        model.addAttribute("pageTitle", __lang("SMS GATEWAYS"));
        model.addAttribute("form", form);
        model.addAttribute("enabled", getSetting("sms_enabled"));
        model.addAttribute("gateways", gateways);

        return "admin.smsgateway.index";
    }

    @PostMapping
    public String postIndex(@RequestParam("sms_enabled") String smsEnabled, RedirectAttributes redirectAttributes) {
        SettingTable settingsTable = new SettingTable();
        settingsTable.saveSetting("sms_enabled", smsEnabled);
        redirectAttributes.addFlashAttribute("flash_message", "Settings changed");
        return "redirect:/admin/smsgateway";
    }

    @GetMapping("/edit/{id}")
    public String edit(@PathVariable("id") SmsGateway smsGateway, Model model) {
        Map<String, Object> settings = unserialize(smsGateway.getSettings());
        if (settings == null) {
            settings = new HashMap<>();
        }
        String form = "messaging." + smsGateway.getDirectory() + ".setup";
        model.addAttribute("settings", settings);
        model.addAttribute("form", form);
        model.addAttribute("smsGateway", smsGateway);
        return "admin.smsgateway.edit";
    }

    @PostMapping("/save/{id}")
    public String save(@PathVariable("id") SmsGateway smsGateway, @RequestParam Map<String, String> allParams, RedirectAttributes redirectAttributes) {
        if ("1".equals(allParams.get("default"))) {
            SmsGateway.updateAll("default", 0);
        }

        smsGateway.setProperties(allParams);
        smsGateway.setSettings(serialize(allParams));
        smsGateway.save();

        redirectAttributes.addFlashAttribute("flash_message", __("default.changes-saved"));
        return "redirect:/admin/smsgateway";
    }

    @GetMapping("/install/{gateway}")
    public String install(@PathVariable("gateway") String gateway, RedirectAttributes redirectAttributes) {
        SmsGateway smsGateway = SmsGateway.findByDirectory(gateway);
        if (smsGateway == null) {
            Map<String, String> info = smsInfo(gateway);
            smsGateway = new SmsGateway();
            smsGateway.setGatewayName(info.get("name"));
            smsGateway.setEnabled(1);
            smsGateway.setDirectory(gateway);
            smsGateway.setSettings(serialize(new HashMap<>()));
            smsGateway.save();
        } else {
            smsGateway.setEnabled(1);
            smsGateway.save();
        }

        redirectAttributes.addFlashAttribute("flash_message", __("default.installed"));
        return "redirect:/admin/smsgateway";
    }

    @PostMapping("/uninstall/{id}")
    public String uninstall(@PathVariable("id") SmsGateway smsGateway, RedirectAttributes redirectAttributes) {
        smsGateway.setEnabled(0);
        smsGateway.save();
        redirectAttributes.addFlashAttribute("flash_message", __lang("Gateway uninstalled"));
        return "redirect:/admin/smsgateway";
    }

    private BaseForm getSmsForm() {
        BaseForm form = new BaseForm();
        form.createCheckbox("sms_enabled", "Enable SMS?", "1");
        return form;
    }
}

