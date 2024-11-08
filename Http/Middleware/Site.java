package com.example.demo.security;

import org.springframework.stereotype.Component;
import org.springframework.web.filter.OncePerRequestFilter;

import javax.servlet.FilterChain;
import javax.servlet.ServletException;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;

@Component
public class SiteFilter extends OncePerRequestFilter {

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                    HttpServletResponse response,
                                    FilterChain filterChain) throws ServletException, IOException {
        // Set GLOBAL_ACCESS as a request attribute
        request.setAttribute("GLOBAL_ACCESS", true);
        
        // Proceed with the next filter or request handler
        filterChain.doFilter(request, response);
    }
}
