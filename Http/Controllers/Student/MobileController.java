package com.app.controllers.student;

import com.app.controllers.Controller;
import com.app.lib.HelperTrait;
import com.app.lib.UtilityFunctions;
import com.app.models.Invoice;
import com.app.models.Student;
import com.laminas.eventmanager.EventManagerInterface;
import com.laminas.session.Container;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import javax.servlet.http.HttpServletRequest;
import java.io.IOException;
import java.time.Instant;
import java.util.HashMap;
import java.util.Map;

@RestController
@RequestMapping("/student/mobile")
public class MobileController extends Controller {

    private HelperTrait helperTrait;

    @Override
    public void setEventManager(EventManagerInterface events) {
        super.setEventManager(events);
        MobileController controller = this;
        events.attach("dispatch", e -> controller.setLayout("layout/mobile"), 100);
    }

    @GetMapping("/load")
    public ResponseEntity<Object> load(HttpServletRequest request) {
        String token = request.getParameter("token");
        String invoiceId = request.getParameter("invoice");

        // Get user with token
        Student student = Student.findByApiTokenAndTokenExpiresAfter(token.trim(), Instant.now());
        if (student == null) {
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).body("Invalid Token");
        }

        // Log student in
        UtilityFunctions.setRole("student");

        Map<String, String> authData = new HashMap<>();
        authData.put("email", student.getEmail().trim());
        authData.put("role", "student");
        getAuthService().getStorage().write(authData);

        // Create cart
        Invoice invoice = Invoice.findById(Long.parseLong(invoiceId));
        if (invoice == null || !student.getStudentId().equals(invoice.getStudentId())) {
            return ResponseEntity.status(HttpStatus.BAD_REQUEST).body("Invalid invoice");
        }

        Cart cart = deserialize(invoice.getCart());
        cart.setInvoice(Long.parseLong(invoiceId));
        cart.store();

        Container session = new Container("client");
        session.set("type", "mobile");

        // Get payment method
        String paymentMethod = invoice.getPaymentMethod().getCode();
        
        // This part might need adjustment based on your specific implementation
        Object viewModel = forward().dispatch("Application\\Controller\\Method", "action", paymentMethod);
        setLayout("layout/mobile");
        return ResponseEntity.ok(viewModel);
    }

    @GetMapping("/close")
    public ResponseEntity<String> close(HttpServletRequest request) {
        return ResponseEntity.ok("close");
    }

    private AuthService getAuthService() {
        return getServiceLocator().get("StudentAuthService");
    }

    // Helper method to deserialize cart (implementation depends on your serialization method)
    private Cart deserialize(String serializedCart) {
        // Implement deserialization logic here
        return null;
    }
}
