package com.example.demo.controller;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.servlet.ModelAndView;

import com.example.demo.service.EmailService; // A service to handle email notifications
import com.example.demo.service.UserService; // A service to handle user operations

@RestController
public class ForgotPasswordController {

    @Autowired
    private EmailService emailService; // Service for sending emails

    @Autowired
    private UserService userService; // Service for user-related operations

    /**
     * Show the password reset request form.
     *
     * @return ModelAndView for the password reset view.
     */
    @GetMapping("/forgot-password")
    public ModelAndView showForgotPasswordForm() {
        return new ModelAndView("auth/forgot-password"); // Return the view for password reset
    }

    /**
     * Handle password reset email sending.
     *
     * @param email The user's email address.
     * @return Response indicating the outcome of the operation.
     */
    @PostMapping("/forgot-password")
    public String sendResetLink(@RequestParam("email") String email) {
        if (userService.existsByEmail(email)) {
            // Send password reset email
            emailService.sendPasswordResetEmail(email);
            return "Password reset link sent to your email address.";
        } else {
            return "Email address not found.";
        }
    }
}
