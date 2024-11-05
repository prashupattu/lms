package com.example.demo.controller;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.web.authentication.logout.SecurityContextLogoutHandler;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestMapping;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

@Controller
@RequestMapping("/auth")
public class ConfirmPasswordController {

    // Redirect destination for password confirmation
    private static final String REDIRECT_URL = "/home";

    @Autowired
    private UserService userService; // Service to handle user operations

    /**
     * Method to show the password confirmation form.
     */
    @GetMapping("/confirm-password")
    public String showConfirmPasswordForm() {
        return "auth/confirm-password"; // Return the view for password confirmation
    }

    /**
     * Method to handle password confirmation.
     *
     * @param password The password provided by the user.
     * @param model    The model to add attributes to the view.
     * @return Redirects to home if confirmed, back to the form otherwise.
     */
    @PostMapping("/confirm-password")
    public String confirmPassword(@RequestParam("password") String password, Model model) {
        Authentication auth = SecurityContextHolder.getContext().getAuthentication();
        String username = auth.getName(); // Get currently authenticated username

        if (userService.checkPassword(username, password)) {
            // Password is correct; redirect to the home page
            return "redirect:" + REDIRECT_URL;
        } else {
            // Password is incorrect; add error message and show form again
            model.addAttribute("error", "Invalid password. Please try again.");
            return "auth/confirm-password";
        }
    }
}
