package com.example.demo.security;

import org.springframework.stereotype.Component;
import org.springframework.web.filter.OncePerRequestFilter;

import javax.crypto.Cipher;
import javax.crypto.KeyGenerator;
import javax.crypto.SecretKey;
import javax.crypto.spec.SecretKeySpec;
import javax.servlet.FilterChain;
import javax.servlet.ServletException;
import javax.servlet.http.Cookie;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.util.Arrays;
import java.util.List;

@Component
public class EncryptCookiesFilter extends OncePerRequestFilter {

    private static final String ALGORITHM = "AES"; // Example encryption algorithm
    private final List<String> except = Arrays.asList(/* Add cookie names to exclude */);

    private SecretKey secretKey;

    public EncryptCookiesFilter() throws Exception {
        KeyGenerator keyGen = KeyGenerator.getInstance(ALGORITHM);
        keyGen.init(128); // Key size
        this.secretKey = keyGen.generateKey();
    }

    @Override
    protected void doFilterInternal(HttpServletRequest request,
                                    HttpServletResponse response,
                                    FilterChain filterChain) throws ServletException, IOException {
        
        // Encrypt cookies from request and add to response
        Cookie[] cookies = request.getCookies();
        if (cookies != null) {
            for (Cookie cookie : cookies) {
                if (!except.contains(cookie.getName())) {
                    String encryptedValue = encrypt(cookie.getValue());
                    response.addCookie(createEncryptedCookie(cookie.getName(), encryptedValue));
                }
            }
        }

        // Proceed with the next filter
        filterChain.doFilter(request, response);
    }

    private Cookie createEncryptedCookie(String name, String value) {
        Cookie cookie = new Cookie(name, value);
        cookie.setHttpOnly(true);
        cookie.setPath("/");
        return cookie;
    }

    private String encrypt(String value) {
        try {
            Cipher cipher = Cipher.getInstance(ALGORITHM);
            cipher.init(Cipher.ENCRYPT_MODE, secretKey);
            byte[] encrypted = cipher.doFinal(value.getBytes());
            return new String(encrypted); // Ideally, encode this to a safe format (e.g., Base64)
        } catch (Exception e) {
            throw new RuntimeException("Encryption error", e);
        }
    }
}
