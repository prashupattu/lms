package App.Http.Controllers.Admin;

import App.Http.Controllers.Controller;
import App.Http.Requests;
import App.AdminRole;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.ModelAndView;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.validation.annotation.Validated;

import javax.validation.Valid;
import java.util.Map;

@RestController
@RequestMapping("/admin/roles")
public class RolesController extends Controller {

    @Autowired
    private AdminRoleRepository adminRoleRepository;

    /**
     * Display a listing of the resource.
     *
     * @return ModelAndView
     */
    @GetMapping
    public ModelAndView index(@RequestParam(required = false) String filter) {
        int perPage = 25;
        Iterable<AdminRole> roles;

        if (filter != null && !filter.isEmpty()) {
            roles = adminRoleRepository.findAllByFilter(filter, perPage);
        } else {
            roles = adminRoleRepository.findAll(perPage);
        }

        ModelAndView modelAndView = new ModelAndView("admin/roles/index");
        modelAndView.addObject("roles", roles);
        modelAndView.addObject("perPage", perPage);
        return modelAndView;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return ModelAndView
     */
    @GetMapping("/create")
    public ModelAndView create() {
        return new ModelAndView("admin/roles/create");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param request
     * @return ResponseEntity
     */
    @PostMapping
    public ResponseEntity<?> store(@Valid @RequestBody AdminRoleRequest request) {
        AdminRole role = new AdminRole(request.getName());
        adminRoleRepository.save(role);
        role.attachPermissions(request.getPermissions());

        return ResponseEntity.ok().body("Changes saved");
    }

    /**
     * Display the specified resource.
     *
     * @param id
     * @return ModelAndView
     */
    @GetMapping("/{id}")
    public ModelAndView show(@PathVariable int id) {
        AdminRole role = adminRoleRepository.findById(id).orElseThrow(() -> new ResourceNotFoundException("Role not found"));
        ModelAndView modelAndView = new ModelAndView("admin/roles/show");
        modelAndView.addObject("role", role);
        return modelAndView;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param id
     * @return ModelAndView
     */
    @GetMapping("/{id}/edit")
    public ModelAndView edit(@PathVariable int id) {
        if (id == 1) {
            return ResponseEntity.badRequest().body("No role can be modified");
        }
        AdminRole role = adminRoleRepository.findById(id).orElseThrow(() -> new ResourceNotFoundException("Role not found"));
        ModelAndView modelAndView = new ModelAndView("admin/roles/edit");
        modelAndView.addObject("role", role);
        return modelAndView;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param request
     * @param id
     * @return ResponseEntity
     */
    @PutMapping("/{id}")
    public ResponseEntity<?> update(@Valid @RequestBody AdminRoleRequest request, @PathVariable int id) {
        if (id == 1) {
            return ResponseEntity.badRequest().body("No role can be modified");
        }
        AdminRole role = adminRoleRepository.findById(id).orElseThrow(() -> new ResourceNotFoundException("Role not found"));
        role.update(request.getName());
        role.syncPermissions(request.getPermissions());

        return ResponseEntity.ok().body("Changes saved");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param id
     * @return ResponseEntity
     */
    @DeleteMapping("/{id}")
    public ResponseEntity<?> destroy(@PathVariable int id) {
        if (id < 3) {
            return ResponseEntity.badRequest().body("No role can be deleted");
        }
        adminRoleRepository.deleteById(id);
        return ResponseEntity.ok().body("Record deleted");
    }
}

