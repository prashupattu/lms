// KernelConfig.java
package com.example.demo.config;

import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.web.servlet.config.annotation.InterceptorRegistry;
import org.springframework.web.servlet.config.annotation.WebMvcConfigurer;
import com.example.demo.middleware.TrustProxiesInterceptor;
import com.example.demo.middleware.HandleCorsInterceptor;
import com.example.demo.middleware.CheckForMaintenanceInterceptor;

@Configuration
public class KernelConfig implements WebMvcConfigurer {

    @Override
    public void addInterceptors(InterceptorRegistry registry) {
        registry.addInterceptor(trustProxiesInterceptor());
        registry.addInterceptor(handleCorsInterceptor());
        registry.addInterceptor(checkForMaintenanceInterceptor());
    }

    @Bean
    public TrustProxiesInterceptor trustProxiesInterceptor() {
        return new TrustProxiesInterceptor();
    }

    @Bean
    public HandleCorsInterceptor handleCorsInterceptor() {
        return new HandleCorsInterceptor();
    }

    @Bean
    public CheckForMaintenanceInterceptor checkForMaintenanceInterceptor() {
        return new CheckForMaintenanceInterceptor();
    }
}
