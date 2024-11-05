package com.example.demo.security;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Component;
import org.springframework.web.filter.OncePerRequestFilter;

import javax.servlet.FilterChain;
import javax.servlet.ServletException;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.util.Arrays;
import java.util.List;

@Component
public class MaintenanceModeFilter extends OncePerRequestFilter {

    @Value("${app.maintenance.mode:false}") // Flag to check if maintenance mode is active
    private boolean maintenanceMode;

    @Value("${app.maintenance.except:}") // Comma-separated list of URIs to bypass maintenance
    private String[] exceptUris;

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                    HttpServletResponse response,
                                    FilterChain filterChain) throws ServletException, IOException {
        if (maintenanceMode) {
            String requestUri = request.getRequestURI();
            List<String> exceptList = Arrays.asList(exceptUris);

            // Check if the request URI is in the except list
            if (!exceptList.contains(requestUri)) {
                response.sendError(HttpServletResponse.SC_SERVICE_UNAVAILABLE, "Service is under maintenance.");
                return;
            }
        }

        // Proceed with the next filter
        filterChain.doFilter(request, response);
    }
}
