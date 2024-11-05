package com.example.demo.controller;

import com.example.demo.model.Certificate;
import com.example.demo.model.Course;
import com.example.demo.model.Currency;
import com.example.demo.model.Invoice;
import com.example.demo.model.PaymentMethod;
import com.example.demo.model.Student;
import com.example.demo.service.CartService; // Service to handle cart logic
import com.example.demo.service.CurrencyService; // Service for currency-related operations
import com.example.demo.service.InvoiceService; // Service for invoice-related operations
import com.example.demo.service.PaymentMethodService; // Service for payment methods
import com.example.demo.service.StudentService; // Service for student-related operations
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.validation.annotation.Validated;
import org.springframework.web.bind.annotation.*;

import javax.servlet.http.HttpSession;

@Controller
@RequestMapping("/cart")
public class CartController {

    @Autowired
    private CartService cartService; // Assume this service handles cart logic

    @Autowired
    private PaymentMethodService paymentMethodService; // Service for payment methods

    @Autowired
    private CurrencyService currencyService; // Service for currency management

    @Autowired
    private InvoiceService invoiceService; // Service for invoice management

    @Autowired
    private StudentService studentService; // Service for student management

    @GetMapping
    public String index(Model model) {
        Cart cart = cartService.getCart();
        model.addAttribute("cart", cart);
        model.addAttribute("paymentMethods", paymentMethodService.getMethodsForCurrency(cart.getCurrencyId()));
        model.addAttribute("currencies", currencyService.getAllCurrencies());
        return "site/cart/index"; // Return the cart view
    }

    @PostMapping("/currency/{currencyId}")
    public String setCurrency(@PathVariable Long currencyId, HttpSession session) {
        session.setAttribute("currency", currencyId);
        return "redirect:/cart";
    }

    @PostMapping("/apply-discount")
    public String applyDiscount(@RequestParam String code) {
        String msg = cartService.applyDiscount(code);
        // Flash message logic here (not shown for brevity)
        return "redirect:/cart";
    }

    @PostMapping("/add/course/{courseId}")
    public String addCourse(@PathVariable Long courseId) {
        if (!cartService.canEnroll(courseId)) {
            return "redirect:/cart";
        }
        cartService.addCourse(courseId);
        return "redirect:/cart";
    }

    @PostMapping("/add/certificate/{certificateId}")
    public String addCertificate(@PathVariable Long certificateId) {
        if (!studentService.canAccessCertificate(certificateId)) {
            return "redirect:/cart";
        }
        cartService.addCertificate(certificateId);
        return "redirect:/cart";
    }

    @PostMapping("/remove/course/{courseId}")
    public String removeCourse(@PathVariable Long courseId) {
        cartService.removeCourse(courseId);
        // Flash message logic here (not shown for brevity)
        return "redirect:/cart";
    }

    @PostMapping("/remove/certificate/{certificateId}")
    public String removeCertificate(@PathVariable Long certificateId) {
        cartService.removeCertificate(certificateId);
        // Flash message logic here (not shown for brevity)
        return "redirect:/cart";
    }

    @PostMapping("/remove/coupon")
    public String removeCoupon() {
        cartService.removeDiscount();
        return "redirect:/cart";
    }

    @PostMapping("/process")
    public String processPayment(@RequestParam Long paymentMethodId) {
        Cart cart = cartService.getCart();
        if (cart.requiresPayment()) {
            // Add validation logic here (not shown for brevity)
        }
        cart.setPaymentMethod(paymentMethodId);
        return "redirect:/cart/checkout";
    }

    @GetMapping("/checkout")
    public String checkout(Model model) {
        Cart cart = cartService.getCart();
        Long studentId = studentService.getCurrentStudentId();
        if (!cart.requiresPayment()) {
            double total = cart.approve(studentId);
            // Flash message logic here (not shown for brevity)
            return "redirect:/student/my-sessions";
        }

        if (!cart.hasItems() || !cart.getPaymentMethod()) {
            return "redirect:/cart";
        }

        if (!invoiceService.createOrUpdateInvoice(cart, studentId)) {
            // Handle error case
            return "redirect:/cart";
        }

        return "payment"; // Redirect to payment processing
    }

    @PostMapping("/callback/{code}")
    public String paymentCallback(@PathVariable String code) {
        // Implement payment gateway callback handling logic
        return "redirect:/cart";
    }

    @PostMapping("/ipn/{code}")
    public String paymentIPN(@PathVariable String code) {
        // Implement payment gateway IPN handling logic
        return "redirect:/cart";
    }

    @PostMapping("/complete")
    public String completeCheckout() {
        cartService.clearCart();
        return "site/cart/complete"; // Return completion view
    }

    // Additional methods for handling mobile requests would go here...
}
