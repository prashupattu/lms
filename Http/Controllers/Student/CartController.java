import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import com.example.app.model.Cart;
import com.example.app.model.Course;
import com.example.app.model.PaymentMethod;
import com.example.app.repository.CartRepository;
import com.example.app.repository.CourseRepository;
import com.example.app.repository.PaymentMethodRepository;
import com.example.app.util.HelperTrait;
import com.example.app.util.SessionUtil;
import java.util.List;

@RestController
@RequestMapping("/student/cart")
public class CartController {

    @Autowired
    private CartRepository cartRepository;

    @Autowired
    private CourseRepository courseRepository;

    @Autowired
    private PaymentMethodRepository paymentMethodRepository;

    @Autowired
    private HelperTrait helperTrait;

    @Autowired
    private SessionUtil sessionUtil;

    @GetMapping
    public ResponseEntity<Cart> index() {
        if (sessionUtil.isMobileApp()) {
            return new ResponseEntity<>(HttpStatus.FOUND);
        }

        Cart cart = cartRepository.getCart();
        return new ResponseEntity<>(cart, HttpStatus.OK);
    }

    @PostMapping
    public ResponseEntity<String> applyDiscount(@RequestParam("code") String discount) {
        Cart cart = cartRepository.getCart();
        String message = cart.applyDiscount(discount);
        return new ResponseEntity<>(message, HttpStatus.OK);
    }

    @PostMapping("/session")
    public ResponseEntity<Void> setSession(@RequestParam("id") Long id) {
        if (!canEnrollToSession(id)) {
            return new ResponseEntity<>(HttpStatus.BAD_REQUEST);
        }

        Course course = courseRepository.findById(id).orElse(null);
        if ((course.getPaymentRequired() == null || course.getAmount() == 0) && (course.getEnrollmentCloses() == null || course.getEnrollmentCloses() > System.currentTimeMillis()) && course.getSessionStatus() != null) {
            return new ResponseEntity<>(HttpStatus.FOUND);
        }

        Cart cart = cartRepository.getCart();
        cart.addSession(id);
        return new ResponseEntity<>(HttpStatus.FOUND);
    }

    @DeleteMapping("/session/{id}")
    public ResponseEntity<Void> removeSession(@PathVariable("id") Long id) {
        Cart cart = cartRepository.getCart();
        cart.removeSession(id);
        return new ResponseEntity<>(HttpStatus.OK);
    }

    @DeleteMapping("/coupon")
    public ResponseEntity<Void> removeCoupon() {
        Cart cart = cartRepository.getCart();
        cart.removeDiscount();
        return new ResponseEntity<>(HttpStatus.OK);
    }

    @PostMapping("/checkout")
    public ResponseEntity<Void> checkout(@RequestParam("payment_method") Long methodId) {
        Cart cart = cartRepository.getCart();
        PaymentMethod method = paymentMethodRepository.findById(methodId).orElse(null);
        cart.setPaymentMethod(method);
        return new ResponseEntity<>(HttpStatus.FOUND);
    }

    @DeleteMapping("/clear")
    public ResponseEntity<Void> clear() {
        Cart cart = cartRepository.getCart();
        cart.clear();
        if (sessionUtil.getType().equals("mobile")) {
            return new ResponseEntity<>(HttpStatus.FOUND);
        } else {
            return new ResponseEntity<>(HttpStatus.FOUND);
        }
    }

    private boolean canEnrollToSession(Long id) {
        // Implement the logic to check if the student can enroll to the session
        return true;
    }
}