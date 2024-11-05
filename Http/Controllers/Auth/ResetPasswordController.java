package com.example.demo.controller;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.servlet.ModelAndView;

import com.example.demo.service.UserService; // Service to handle user operations

@RestController
public class ResetPasswordController {

    @Autowired
    private UserService userService; // Service for user-related operations

    @Autowired
    private PasswordEncoder passwordEncoder; // For encoding passwords

    /**
     * Handle password reset requests.
     *
     * @param token    The reset token sent to the user's email.
     * @param password The new password provided by the user.
     * @return Response indicating the outcome of the operation.
     */
    @PostMapping("/reset-password")
    public String resetPassword(@RequestParam("token") String token, 
                                @RequestParam("password") String password) {
        // Verify the token and update the password
        if (userService.validatePasswordResetToken(token)) {
            String username = userService.getUsernameByToken(token); // Get username from token
            userService.updatePassword(username, passwordEncoder.encode(password)); // Update the password
            return "Password has been reset successfully.";
        } else {
            return "Invalid or expired password reset token.";
        }
    }

    /**
     * Show the reset password form.
     *
     * @return ModelAndView for the password reset view.
     */
    @GetMapping("/reset-password")
    public ModelAndView showResetPasswordForm(@RequestParam("token") String token) {
        // Validate the token before showing the form
        if (userService.validatePasswordResetToken(token)) {
            return new ModelAndView("auth/reset-password"); // Return the view for password reset
        } else {
            return new ModelAndView("error").addObject("message", "Invalid or expired token.");
        }
    }
}
