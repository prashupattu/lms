package com.example.admin.controllers;

import com.example.models.*;
import com.example.services.*;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestMapping;

import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.List;
import java.util.stream.IntStream;

@Controller
@RequestMapping("/admin")
public class HomeController {

    @Autowired
    private UserService userService;

    @Autowired
    private SessionService sessionService;

    @Autowired
    private LessonService lessonService;

    @Autowired
    private TestService testService;

    @Autowired
    private StudentController studentController;

    @Autowired
    private DiscussController discussController;

    @Autowired
    private InvoiceService invoiceService;

    @GetMapping("/")
    public String index(Model model) {
        // Get total students
        long totalStudents = userService.countStudents();
        model.addAttribute("totalStudents", totalStudents);

        // Get total sessions and courses
        long totalSessions = sessionService.countSessions();
        long totalCourses = sessionService.countCourses();
        model.addAttribute("totalSessions", totalSessions);
        model.addAttribute("totalCourses", totalCourses);

        // Get total classes
        long totalClasses = lessonService.getTotal();
        model.addAttribute("totalClasses", totalClasses);

        // Get total tests
        long totalTests = testService.countActiveTests();
        model.addAttribute("totalTests", totalTests);

        // Get student data
        model.addAttribute("student", studentController.getStudentData());

        // Get session data
        model.addAttribute("session", studentController.getSessionData());

        // Get discuss data
        model.addAttribute("discuss", discussController.getDiscussData(false));

        // Sales data
        LocalDate today = LocalDate.now();
        model.addAttribute("todaySales", invoiceService.countPaidInvoicesForDay(today));
        model.addAttribute("todaySum", invoiceService.getTotalForDay(today));

        LocalDate weekStart = today.minusDays(today.getDayOfWeek().getValue() - 1);
        LocalDate weekEnd = weekStart.plusDays(6);
        model.addAttribute("weekSales", invoiceService.countPaidInvoicesForDateRange(weekStart, weekEnd));
        model.addAttribute("weekSum", invoiceService.getTotalForDateRange(weekStart, weekEnd));

        LocalDate monthStart = today.withDayOfMonth(1);
        model.addAttribute("monthSales", invoiceService.countPaidInvoicesForDateRange(monthStart, today));
        model.addAttribute("monthSum", invoiceService.getTotalForDateRange(monthStart, today));

        LocalDate yearStart = today.withDayOfYear(1);
        model.addAttribute("yearSales", invoiceService.countPaidInvoicesForDateRange(yearStart, today));
        model.addAttribute("yearSum", invoiceService.getTotalForDateRange(yearStart, today));

        // Monthly data
        List<String> months = getLastEightMonths();
        List<Double> monthlySales = new ArrayList<>();
        List<Long> monthlyCount = new ArrayList<>();
        List<Long> monthlyCompCount = new ArrayList<>();
        List<Long> monthlyCanCount = new ArrayList<>();

        for (int i = -7; i <= 0; i++) {
            LocalDate start = today.plusMonths(i).withDayOfMonth(1);
            LocalDate end = start.plusMonths(1).minusDays(1);
            monthlySales.add(invoiceService.getTotalForDateRange(start, end));
            monthlyCount.add(invoiceService.countPaidInvoicesForDateRange(start, end));
            monthlyCompCount.add(invoiceService.countCompletedInvoicesForDateRange(start, end));
            monthlyCanCount.add(invoiceService.countCancelledInvoicesForDateRange(start, end));
        }

        model.addAttribute("monthSaleData", monthlySales);
        model.addAttribute("monthSaleCount", monthlyCount);
        model.addAttribute("monthSaleCompCount", monthlyCompCount);
        model.addAttribute("monthSaleCanCount", monthlyCanCount);
        model.addAttribute("monthList", months);

        // Latest users and invoices
        model.addAttribute("latestUsers", userService.getLatestUsers(6));
        model.addAttribute("invoices", invoiceService.getLatestInvoices(10));

        return "admin/home/index";
    }

    private List<String> getLastEightMonths() {
        List<String> months = new ArrayList<>();
        LocalDate date = LocalDate.now();
        DateTimeFormatter formatter = DateTimeFormatter.ofPattern("MMM");
        for (int i = -7; i <= 0; i++) {
            months.add(date.plusMonths(i).format(formatter));
        }
        return months;
    }
}

