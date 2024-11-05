package com.example.demo.security;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Component;
import org.springframework.web.filter.OncePerRequestFilter;

import javax.servlet.FilterChain;
import javax.servlet.ServletException;
import javax.servlet.http.Cookie;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import java.io.IOException;

@Component
public class StartSessionFilter extends OncePerRequestFilter {

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                    HttpServletResponse response,
                                    FilterChain filterChain) throws ServletException, IOException {
        // Start a new session or retrieve the existing one
        HttpSession session = request.getSession(true);

        String sessionCookieName = "JSESSIONID"; // Default session cookie name in Spring

        // Check if the session cookie is present in the request
        if (request.getParameter(sessionCookieName) != null) {
            String sessionId = request.getParameter(sessionCookieName);
            session.setId(sessionId);
        } else {
            // Check for session ID in cookies
            Cookie[] cookies = request.getCookies();
            if (cookies != null) {
                for (Cookie cookie : cookies) {
                    if (sessionCookieName.equals(cookie.getName())) {
                        session.setId(cookie.getValue());
                        break;
                    }
                }
            }
        }

        // Proceed with the next filter
        filterChain.doFilter(request, response);
    }
}
