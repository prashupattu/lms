package com.example.middleware;

import javax.servlet.Filter;
import javax.servlet.FilterChain;
import javax.servlet.FilterConfig;
import javax.servlet.ServletException;
import javax.servlet.ServletRequest;
import javax.servlet.ServletResponse;
import javax.servlet.http.HttpServletRequest;
import java.io.IOException;
import java.util.Arrays;
import java.util.List;
import java.util.regex.Pattern;

public class CsrfFilter implements Filter {

    // List of URIs that should be excluded from CSRF verification
    private static final List<Pattern> EXCLUDED_PATHS = Arrays.asList(
        Pattern.compile("/admin/filemanager/.*"),
        Pattern.compile("/ipn/.*"),
        Pattern.compile("/cart/callback/.*"),
        Pattern.compile("/cart/.*"),
        Pattern.compile("/cart"),
        Pattern.compile("/setup"),
        Pattern.compile("/student/test/.*")
    );

    @Override
    public void init(FilterConfig filterConfig) throws ServletException {
        // Initialization logic, if needed
    }

    @Override
    public void doFilter(ServletRequest request, ServletResponse response, FilterChain chain) throws IOException, ServletException {
        HttpServletRequest httpRequest = (HttpServletRequest) request;
        String requestURI = httpRequest.getRequestURI();

        // Check if request URI matches any excluded path
        boolean isExcluded = EXCLUDED_PATHS.stream().anyMatch(pattern -> pattern.matcher(requestURI).matches());

        if (!isExcluded) {
            // Perform CSRF token verification
            // Implement CSRF check logic here, e.g., validate CSRF token in the request
            // If CSRF token is invalid, respond with an error
        }

        // Continue with the request
        chain.doFilter(request, response);
    }

    @Override
    public void destroy() {
        // Cleanup logic, if needed
    }
}
