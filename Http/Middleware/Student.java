package com.example.demo.security;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.stereotype.Component;
import org.springframework.web.filter.OncePerRequestFilter;

import javax.servlet.FilterChain;
import javax.servlet.ServletException;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.time.LocalDateTime;

@Component
public class StudentFilter extends OncePerRequestFilter {

    @Autowired
    private UserService userService; // Assume you have a service for user-related operations

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                    HttpServletResponse response,
                                    FilterChain filterChain) throws ServletException, IOException {
        Authentication authentication = SecurityContextHolder.getContext().getAuthentication();

        if (authentication != null && authentication.isAuthenticated()) {
            User user = (User) authentication.getPrincipal(); // Assume User is your user entity

            // Set last seen value
            user.setLastSeen(LocalDateTime.now());
            userService.updateUser(user); // Persist the changes

            // Check if the user role is not 2 (assuming role ID 2 is for students)
            if (user.getRoleId() != 2) {
                response.sendRedirect("/login");
                return;
            }

            // Set global access (could be a request attribute instead)
            request.setAttribute("GLOBAL_ACCESS", true);

            // Save redirect link (implement setRedirectLink method accordingly)
            setRedirectLink(request.getRequestURI());
        }

        // Proceed with the next filter
        filterChain.doFilter(request, response);
    }

    private void setRedirectLink(String requestUri) {
        // Implement your logic to save the redirect link
    }
}
