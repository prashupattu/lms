package com.example.demo.security;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.web.filter.OncePerRequestFilter;
import org.springframework.web.servlet.HandlerMapping;

import javax.servlet.FilterChain;
import javax.servlet.ServletException;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.util.Map;

public class AdminFilter extends OncePerRequestFilter {

    @Autowired
    private PermissionService permissionService; // Assume a service for permissions

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                    HttpServletResponse response,
                                    FilterChain filterChain) throws ServletException, IOException {
        Authentication authentication = SecurityContextHolder.getContext().getAuthentication();
        
        if (authentication == null || !(authentication.getPrincipal() instanceof UserDetails)) {
            response.sendRedirect("/login");
            return;
        }

        UserDetails user = (UserDetails) authentication.getPrincipal();

        // Check if the user is an admin
        if (user.getRoleId() != 1) {
            response.sendRedirect("/login");
            return;
        }

        // Check global access
        boolean globalAccess = user.hasPermission("global_resource_access");
        request.setAttribute("GLOBAL_ACCESS", globalAccess);

        if (user.getRoleId() == 1 && user.isAdmin()) {
            request.setAttribute("ADMIN_ID", user.getAdminId());
        }

        // Check route permissions
        String routeName = (String) request.getAttribute(HandlerMapping.BEST_MATCHING_PATTERN_ATTRIBUTE);
        Map<String, String> permissions = permissionService.getPermissions();

        if (permissions.containsKey(routeName)) {
            String permission = permissions.get(routeName);
            if (!user.hasPermission(permission)) {
                response.sendError(HttpServletResponse.SC_FORBIDDEN);
                return;
            }
        }

        filterChain.doFilter(request, response);
    }
}
