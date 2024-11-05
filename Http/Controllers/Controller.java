package com.example.demo.controllers;

import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.validation.annotation.Validated;

@RestController
@RequestMapping("/api") // Example base URL mapping
@Validated // To enable validation on method parameters
public abstract class BaseController {

    // You can add common methods for your controllers here, such as:
    
    // Example: method for handling authorization
    @PreAuthorize("hasRole('USER')") // Example role-based access control
    public void authorizeUser() {
        // Authorization logic
    }

    // Example: dispatching a job (not directly in the controller, typically handled by a service)
    // Add your job dispatching logic in a service class.
}
