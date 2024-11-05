package com.app.controllers.admin;

import com.app.controllers.Controller;
import com.app.lib.HelperTrait;
import com.app.models.PaymentMethod;
import com.app.models.Template;
import com.app.v2.model.CountryTable;
import org.springframework.web.bind.annotation.*;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

import javax.validation.Valid;
import java.util.*;

@RestController
@RequestMapping("/admin/payment-gateway")
public class PaymentGatewayController extends Controller {
    
    private HelperTrait helperTrait;

    @GetMapping
    public String index(Model model) {
        List<String> methods = getDirectoryContents(PAYMENT_PATH);
        
        Map<String, String> options = new LinkedHashMap<>();
        options.put("ANY", __lang("Any Currency"));
        
        CountryTable countryTable = new CountryTable();
        List<Country> rowset = countryTable.getRecords();
        for (Country row : rowset) {
            options.put(row.getCurrencyCode(), row.getCurrencyName());
        }
        
        model.addAttribute("methods", methods);
        model.addAttribute("options", options);
        return "admin/payment-gateway/index";
    }

    @PostMapping("/install/{method}")
    public String install(@PathVariable String method, RedirectAttributes redirectAttributes) {
        PaymentMethod paymentMethod = PaymentMethod.findByDirectory(method);
        if (paymentMethod == null) {
            Map<String, Object> info = paymentInfo(method);
            paymentMethod = new PaymentMethod();
            paymentMethod.setName(__(info.get("name").toString()));
            paymentMethod.setEnabled(true);
            paymentMethod.setDirectory(method);
            paymentMethod.setSupportedCurrencies(info.get("currencies").toString());
            paymentMethod.setLabel(__(info.get("name").toString()));
            paymentMethod.setIsGlobal(false);
            paymentMethod.save();
        } else {
            paymentMethod.setEnabled(true);
            paymentMethod.save();
        }
        
        redirectAttributes.addFlashAttribute("flash_message", __("default.installed"));
        return "redirect:/admin/payment-gateway";
    }

    @PostMapping("/uninstall/{id}")
    public String uninstall(@PathVariable Long id, RedirectAttributes redirectAttributes) {
        PaymentMethod paymentMethod = PaymentMethod.findById(id);
        paymentMethod.setEnabled(false);
        paymentMethod.save();
        
        redirectAttributes.addFlashAttribute("flash_message", __("default.uninstalled"));
        return "redirect:/admin/payment-gateway";
    }

    @GetMapping("/edit/{id}")
    public String edit(@PathVariable Long id, Model model) {
        PaymentMethod paymentMethod = PaymentMethod.findById(id);
        Map<String, Object> settings = unserialize(paymentMethod.getSettings());
        if (settings == null) {
            settings = new HashMap<>();
        }
        
        String form = "payment." + paymentMethod.getDirectory() + ".views.setup";
        model.addAttribute("settings", settings);
        model.addAttribute("form", form);
        model.addAttribute("paymentMethod", paymentMethod);
        return "admin/payment-gateway/edit";
    }

    @PostMapping("/save/{id}")
    public String save(@PathVariable Long id, @Valid @ModelAttribute PaymentMethod paymentMethod, 
                       BindingResult bindingResult, RedirectAttributes redirectAttributes) {
        if (bindingResult.hasErrors()) {
            return "admin/payment-gateway/edit";
        }
        
        PaymentMethod existingPaymentMethod = PaymentMethod.findById(id);
        existingPaymentMethod.setLabel(paymentMethod.getLabel());
        existingPaymentMethod.setSettings(serialize(paymentMethod));
        existingPaymentMethod.save();
        
        redirectAttributes.addFlashAttribute("flash_message", __("default.changes-saved"));
        return "redirect:/admin/payment-gateway";
    }
}

