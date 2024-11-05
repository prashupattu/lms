package com.example.demo.controller;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.servlet.ModelAndView;

import com.example.demo.model.User; // Assuming you have a User model
import com.example.demo.service.EmailService; // Service for sending emails
import com.example.demo.service.UserService; // Service for user-related operations

@RestController
public class VerificationController {

    @Autowired
    private UserService userService; // Service for user-related operations

    @Autowired
    private EmailService emailService; // Service for sending emails

    /**
     * Handle email verification.
     *
     * @param token The verification token.
     * @return Response indicating the outcome of the verification.
     */
    @GetMapping("/email/verify")
    public String verifyEmail(@RequestParam("token") String token, @AuthenticationPrincipal User user) {
        if (userService.verifyEmail(token, user)) {
            return "Email verified successfully.";
        } else {
            return "Invalid verification token.";
        }
    }

    /**
     * Resend the verification email.
     *
     * @param user The authenticated user.
     * @return Response indicating the outcome of the resend request.
     */
    @PostMapping("/email/resend")
    public String resendVerificationEmail(@AuthenticationPrincipal User user) {
        if (!user.isEmailVerified()) {
            emailService.sendVerificationEmail(user);
            return "Verification email sent.";
        } else {
            return "Email is already verified.";
        }
    }

    /**
     * Show the verification form.
     *
     * @return ModelAndView for the verification view.
     */
    @GetMapping("/email/verify/form")
    public ModelAndView showVerificationForm() {
        return new ModelAndView("auth/verify-email"); // Return the view for email verification
    }
}
