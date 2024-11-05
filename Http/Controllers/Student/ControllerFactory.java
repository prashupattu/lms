package com.example.demo.factories;

import com.example.demo.controllers.StudentController; // Replace with your actual controller
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.beans.factory.FactoryBean;
import org.springframework.stereotype.Component;

@Component
public class ControllerFactory implements FactoryBean<StudentController> {

    @Autowired
    private ApplicationContext context; // You can inject the ApplicationContext if needed

    @Override
    public StudentController getObject() throws Exception {
        return context.getBean(StudentController.class);
    }

    @Override
    public Class<?> getObjectType() {
        return StudentController.class;
    }

    @Override
    public boolean isSingleton() {
        return true; // Adjust based on your needs (singleton or prototype)
    }
}
