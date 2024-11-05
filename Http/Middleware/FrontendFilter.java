package com.example.demo.security;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Component;
import org.springframework.web.filter.OncePerRequestFilter;

import javax.servlet.FilterChain;
import javax.servlet.ServletException;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.File;
import java.io.IOException;

@Component
public class FrontendFilter extends OncePerRequestFilter {

    @Value("${app.storage.path:../storage/}") // Base path for storage
    private String storagePath;

    @Value("${app.frontend.status:1}") // Default status
    private String frontendStatus;

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                    HttpServletResponse response,
                                    FilterChain filterChain) throws ServletException, IOException {
        // Check if the installation file exists
        File installedFile = new File(storagePath + "installed");
        if (installedFile.exists()) {
            // Check the frontend status
            if ("0".equals(frontendStatus)) {
                response.sendRedirect("/home");
                return;
            }
        }

        // Proceed with the next filter
        filterChain.doFilter(request, response);
    }
}
