package App.Http.Controllers.Admin;

import App.Coupon;
import App.Currency;
import App.Http.Controllers.Controller;
import App.Lib.BaseForm;
import App.Lib.HelperTrait;
import App.PaymentMethod;
import App.V2.Model.SessionCategoryTable;
import App.V2.Model.SessionTable;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.ModelAndView;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMethod;

import java.util.HashMap;
import java.util.List;
import java.util.Map;

@RestController
@RequestMapping("/admin/payment")
public class PaymentController extends Controller {

    @Autowired
    private HelperTrait helperTrait;

    @GetMapping
    public ModelAndView index(@RequestParam Map<String, String> requestParams) {
        PaymentMethodTable table = new PaymentMethodTable();
        CountryTable countryTable = new CountryTable();

        Paginator paginator = table.getPaginatedRecords(true);
        paginator.setCurrentPageNumber(Integer.parseInt(requestParams.getOrDefault("page", "1")));
        paginator.setItemCountPerPage(30);

        Select select = new Select("currency");
        select.setAttribute("id", "currencyselect")
                .setAttribute("class", "form-control")
                .setAttribute("data-sort", "currency");
        select.setEmptyOption(__lang("Select Currency"));

        Map<String, String> options = new HashMap<>();
        options.put("ANY", __lang("Any Currency"));
        List<Country> rowset = countryTable.getRecords();
        for (Country row : rowset) {
            options.put(row.getCurrencyCode(), row.getCurrencyName());
        }
        select.setValueOptions(options);
        return viewModel("admin", getClass(), "index", Map.of(
                "paginator", paginator,
                "pageTitle", __lang("Payment Methods"),
                "select", select
        ));
    }

    @GetMapping("/edit")
    public Map<String, Object> edit(@RequestParam Map<String, String> requestParams) {
        PaymentMethodTable paymentMethodTable = new PaymentMethodTable();
        PaymentMethodFieldTable fieldsTable = new PaymentMethodFieldTable();
        String id = requestParams.get("id");
        PaymentMethod pmRow = paymentMethodTable.getRecord(id);
        PaymentMethodForm form = new PaymentMethodForm(null, this.getServiceLocator(), id);
        Map<String, Object> output = new HashMap<>();
        List<Field> fields = fieldsTable.getRecordsForMethod(id);
        fields.buffer();

        if ("POST".equalsIgnoreCase(requestParams.get("_method"))) {
            Map<String, String> data = requestParams;
            paymentMethodTable.update(Map.of(
                    "status", data.get("status"),
                    "sort_order", data.get("sort_order"),
                    "method_label", data.get("method_label"),
                    "is_global", data.get("is_global")
            ), id);

            for (Field row : fields) {
                fieldsTable.updateValue(data.get(row.getKey()), row.getKey(), id);
            }

            session().flash("flash_message", __lang("pm-settings-saved", Map.of("paymentMethod", pmRow.getPaymentMethod())));
            return adminRedirect(Map.of("controller", "payment", "action", "index"));
        } else {
            Map<String, String> formData = new HashMap<>();
            for (Field row : fields) {
                formData.put(row.getKey(), row.getValue());
            }
            formData.put("status", pmRow.getStatus());
            formData.put("sort_order", pmRow.getSortOrder());
            formData.put("method_label", pmRow.getMethodLabel());
            formData.put("is_global", pmRow.getIsGlobal());
            form.setData(formData);
        }
        output.put("fields", fields);
        output.put("form", form);
        output.put("pageTitle", __lang("Edit Payment Method") + ": " + pmRow.getPaymentMethod());
        output.put("id", id);

        return output;
    }

    @PostMapping("/currencies/{id}")
    public ModelAndView currencies(@RequestParam Map<String, String> requestParams, @PathVariable String id) {
        List<Currency> currencies = Currency.get();
        PaymentMethod paymentMethod = PaymentMethod.findOrFail(id);
        if ("POST".equalsIgnoreCase(requestParams.get("_method"))) {
            String currency = requestParams.get("currency");
            if (paymentMethod.currencies().where("id", currency).first() == null) {
                paymentMethod.currencies().attach(currency);
            }
        }

        List<Currency> rowset = paymentMethod.currencies();
        return view("admin.payment.currencies", Map.of("currencies", currencies, "rowset", rowset, "paymentMethod", paymentMethod));
    }

    @PostMapping("/deletecurrency/{id}")
    public ModelAndView deleteCurrency(@RequestParam Map<String, String> requestParams, @PathVariable PaymentMethod paymentMethod, @PathVariable String id) {
        paymentMethod.currencies().detach(id);
        return app(PaymentController.class).currencies(requestParams, paymentMethod.getId());
    }

    @GetMapping("/coupons")
    public ModelAndView coupons() {
        this.data.put("coupons", Coupon.orderBy("id", "desc").paginate(20));
        this.data.put("pageTitle", __lang("Coupons"));
        return view("admin.payment.coupons", this.data);
    }

    @PostMapping("/addcoupon")
    public ModelAndView addCoupon(@RequestParam Map<String, String> requestParams) {
        BaseForm form = couponForm();

        if ("POST".equalsIgnoreCase(requestParams.get("_method"))) {
            Map<String, String> formData = requestParams;
            form.setData(formData);
            if (form.isValid()) {
                Map<String, String> data = form.getData();
                data = setNull(data);
                data.put("expires_on", getDateString(data.get("expires")));
                data.put("date_start", getDateString(data.get("date_start")));
                data.put("code", trim(strtolower(safeUrl(data.get("code")))));
                if (Coupon.where("code", data.get("code")).count() > 0) {
                    return back().with("flash_message", __lang("default.code-exists"));
                }

                data.put("discount", checkDiscount(data.get("discount"), data.get("type")));
                Coupon coupon = Coupon.create(data);
                coupon.courses().attach(requestParams.get("sessions"));
                coupon.courseCategories().attach(requestParams.get("categories"));

                session().flash("flash_message", __lang("Coupon created"));
                return adminRedirect(Map.of("controller", "payment", "action", "coupons"));
            } else {
                this.data.put("flash_message", getFormErrors(form));
            }
        }

        this.data.put("pageTitle", __lang("Add Coupon"));
        this.data.put("form", form);
        return view("admin.payment.addcoupon", this.data);
    }

    @PostMapping("/editcoupon/{id}")
    public ModelAndView editCoupon(@RequestParam Map<String, String> requestParams, @PathVariable String id) {
        BaseForm form = couponForm();
        Coupon coupon = Coupon.find(id);
        if ("POST".equalsIgnoreCase(requestParams.get("_method"))) {
            Map<String, String> formData = requestParams;
            form.setData(formData);
            if (form.isValid()) {
                Map<String, String> data = form.getData();
                data = setNull(data);

                data.put("expires_on", getDateString(data.get("expires")));
                data.put("date_start", getDateString(data.get("date_start")));
                data.put("code", trim(strtolower(safeUrl(data.get("code")))));

                if (Coupon.where("code", data.get("code")).where("id", "!=", id).count() > 0) {
                    return back().with("flash_message", __lang("default.code-exists"));
                }

                data.put("discount", checkDiscount(data.get("discount"), data.get("type")));

                coupon.fill(data);
                coupon.save();
                coupon.courses().sync(requestParams.get("sessions"));
                coupon.courseCategories().sync(requestParams.get("categories"));
                session().flash("flash_message", __lang("Coupon saved"));
                return adminRedirect(Map.of("controller", "payment", "action", "coupons"));
            } else {
                this.data.put("message", getFormErrors(form));
            }
        } else {
            Map<String, String> data = coupon.toMap();
            data.put("expires", showDate("Y-m-d", coupon.getExpiresOn()));
            data.put("date_start", showDate("Y-m-d", coupon.getDateStart()));

            for (CourseCategory groupRow : coupon.getCourseCategories()) {
                data.put("categories[]", groupRow.getId());
            }

            for (Course groupRow : coupon.getCourses()) {
                data.put("sessions[]", groupRow.getId());
            }

            form.setData(data);
        }

        this.data.put("pageTitle", __lang("Edit Coupon"));
        this.data.put("form", form);
        return viewModel("admin", getClass(), "addcoupon", this.data);
    }

    public Map<String, String> setNull(Map<String, String> data) {
        for (Map.Entry<String, String> entry : data.entrySet()) {
            data.put(entry.getKey(), entry.getValue().isEmpty() ? null : entry.getValue());
        }
        return data;
    }

    private void saveCouponData(Coupon coupon, Map<String, String> data) {
        coupon.couponCategories().delete();
        coupon.couponSessions().delete();

        for (String value : data.get("sessions")) {
            CouponSession.create(Map.of(
                    "coupon_id", coupon.getCouponId(),
                    "session_id", Integer.parseInt(value)
            ));
        }

        for (String value : data.get("categories")) {
            CouponCategory.create(Map.of(
                    "coupon_id", coupon.getCouponId(),
                    "session_category_id", Integer.parseInt(value)
            ));
        }
    }

    private int checkDiscount(int discount, String type) {
        if (discount > 100 && "P".equals(type)) {
            discount = 100;
        } else if (discount < 1) {
            discount = 1;
        }
        return discount;
    }

    @PostMapping("/deletecoupon/{id}")
    public ModelAndView deleteCoupon(@RequestParam Map<String, String> requestParams, @PathVariable String id) {
        Coupon coupon = Coupon.find(id);
        coupon.delete();
        session().flash("flash_message", __lang("Coupon deleted"));
        return back();
    }

    private BaseForm couponForm() {
        BaseForm form = new BaseForm();
        form.createText("code", "Coupon Code", true, null, null, __lang("code-not-case"));
        form.createText("discount", "Discount", true, "form-control digit", null, __lang("Numbers only"));
        form.createText("expires", "End Date", true, "form-control date");

        form.createSelect("enabled", "Status", Map.of(1, __lang("Enabled"), 0, __lang("Disabled")), true, false);
        form.createText("name", "Coupon Name", true);
        form.createSelect("type", "Type", Map.of(
                "P", __lang("Percentage"),
                "F", __lang("Fixed Amount")
        ), true, false);
        form.createText("total", "Total Amount", false, "form-control digit");
        form.createText("date_start", "Start Date", true, "form-control date");
        form.createText("uses_total", "Uses Per Coupon", false, "form-control number");
        form.createText("uses_customer", "Uses Per Customer", false, "form-control number");

        Map<Integer, String> options = new HashMap<>();
        SessionCategoryTable sessionCategoryTable = new SessionCategoryTable();
        List<SessionCategory> rowset = sessionCategoryTable.getLimitedRecords(5000);
        for (SessionCategory row : rowset) {
            options.put(row.getId(), row.getName());
        }

        form.createSelect("categories[]", "Course Categories", options, false);
        form.get("categories[]").setAttribute("multiple", "multiple");
        form.get("categories[]").setAttribute("class", "form-control select2");

        options = new HashMap<>();
        SessionTable sessionTable = new SessionTable();
        rowset = sessionTable.getLimitedRecords(5000);
        for (Session row : rowset) {
            options.put(row.getId(), row.getName());
        }

        form.createSelect("sessions[]", "Courses", options, false);
        form.get("sessions[]").setAttribute("multiple", "multiple");
        form.get("sessions[]").setAttribute("class", "form-control select2");

        form.setInputFilter(couponFilter());
        return form;
    }

    private InputFilter couponFilter() {
        InputFilter filter = new InputFilter();
        filter.add(new InputFilterElement("code", true, List.of(new NotEmptyValidator())));
        filter.add(new InputFilterElement("discount", true, List.of(new NotEmptyValidator())));
        filter.add(new InputFilterElement("expires", true, List.of(new NotEmptyValidator())));
        filter.add(new InputFilterElement("enabled", false));
        filter.add(new InputFilterElement("name", true, List.of(new NotEmptyValidator())));
        filter.add(new InputFilterElement("type", true, List.of(new NotEmptyValidator())));
        filter.add(new InputFilterElement("total", false));
        filter.add(new InputFilterElement("date_start", true, List.of(new NotEmptyValidator())));
        filter.add(new InputFilterElement("uses_total", false));
        filter.add(new InputFilterElement("uses_customer", false));
        filter.add(new InputFilterElement("categories[]", false));
        filter.add(new InputFilterElement("sessions[]", false));

        return filter;
    }
}

