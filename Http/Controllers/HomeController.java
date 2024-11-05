package com.example.demo.controllers;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.stereotype.Controller;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.servlet.ModelAndView;

@Controller
public class HomeController {

    @Autowired
    private UserService userService; // Assuming you have a UserService for user-related operations

    /**
     * Show the application dashboard.
     *
     * @param authentication the authentication object containing user details
     * @return ModelAndView for redirection
     */
    @GetMapping("/home") // Map this to your desired URL
    @PreAuthorize("isAuthenticated()") // Ensure the user is authenticated
    public ModelAndView index(@AuthenticationPrincipal UserDetailsImpl userDetails) {
        ModelAndView modelAndView = new ModelAndView();

        // Fetch the user's role
        User user = userService.findById(userDetails.getId()); // Assuming you have a method to fetch user by ID
        if (user.getRoleId() == 1) {
            modelAndView.setViewName("redirect:/admin/dashboard");
        } else if (user.getRoleId() == 2) {
            modelAndView.setViewName("redirect:/student/dashboard");
        }

        return modelAndView;
    }
}
