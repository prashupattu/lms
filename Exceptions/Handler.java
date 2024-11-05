package App.Exceptions;

import org.springframework.web.bind.annotation.ControllerAdvice;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.context.request.WebRequest;

import java.util.ArrayList;
import java.util.List;

@ControllerAdvice
public class Handler {

    /**
     * A list of the exception types that are not reported.
     */
    protected List<Class<? extends Throwable>> dontReport = new ArrayList<>();

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     */
    protected List<String> dontFlash = List.of("password", "password_confirmation");

    /**
     * Report or log an exception.
     *
     * @param exception the exception to report
     */
    public void report(Throwable exception) {
        // Implement logging logic here
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param request   the web request
     * @param exception the exception to render
     * @return the HTTP response
     */
    @ExceptionHandler(Throwable.class)
    public ResponseEntity<Object> render(WebRequest request, Throwable exception) {
        // Implement rendering logic here
        return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR).body(exception.getMessage());
    }
}

