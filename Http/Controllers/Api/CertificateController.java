package App.Http.Controllers.Api;

import App.Assignment;
import App.Certificate;
import App.Http.Controllers.Controller;
import App.Student;
import App.StudentField;
import App.Test;
import App.V2.Model.AttendanceTable;
import App.V2.Model.CertificateLessonTable;
import App.V2.Model.CertificateTable;
import App.V2.Model.CertificateTestTable;
import App.V2.Model.SessionLessonTable;
import App.V2.Model.SessionTable;
import App.V2.Model.StudentCertificateTable;
import App.V2.Model.StudentFieldTable;
import App.V2.Model.StudentSessionTable;
import App.V2.Model.StudentTestTable;
import com.dompdf.Dompdf;
import com.dompdf.Options;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

@RestController
public class CertificateController extends Controller {

    public ResponseEntity<Map<String, Object>> certificates(@RequestBody Map<String, Object> request) {
        Map<String, Object> params = request;
        StudentSessionTable table = new StudentSessionTable();
        int id = this.getApiStudentId();
        int rowsPerPage = 30;

        int total = table.getTotalCertificates(id);
        int totalPages = (int) Math.ceil((double) total / rowsPerPage);

        int page = params.containsKey("page") ? (int) params.get("page") : 1;

        List<Object> records = new ArrayList<>();

        if (page <= totalPages) {
            Paginator paginator = table.getCertificates(true, id);
            paginator.setCurrentPageNumber(page);
            paginator.setItemCountPerPage(rowsPerPage);

            for (Object row : paginator) {
                ((RowType) row).session_name = ((RowType) row).name; // Assuming RowType is the type of row
                records.add(row);
            }
        }

        Map<String, Object> response = new HashMap<>();
        response.put("total_pages", totalPages);
        response.put("current_page", page);
        response.put("total", total);
        response.put("rows_per_page", rowsPerPage);
        response.put("records", records);

        return ResponseEntity.ok(response);
    }

    public ResponseEntity<Map<String, Object>> getCertificate(@RequestBody Map<String, Object> request, @RequestParam int id) {
        CertificateTable certificateTable = new CertificateTable();

        if (!this.canAccessCertificate(id)) {
            return ResponseEntity.ok(Map.of("status", false, "msg", __lang("not-met-requirements")));
        }

        Student student = this.getApiStudent();
        Certificate certificate = Certificate.findOrFail(id);
        if (certificate.payment_required == 1 && (student.user().certificatePayments().where("certificate_id", id).count() > 0)) {
            return ResponseEntity.ok(Map.of("status", false, "msg", __lang("payment-required")));
        }

        if (!this.canDownload(id)) {
            return ResponseEntity.ok(Map.of("status", false, "msg", __lang("exceeded-max-downloads")));
        }

        String html = this.getCertificateHtml(id);
        if (useDomPdf()) {
            Options options = new Options();
            options.set("isRemoteEnabled", true);
            Dompdf dompdf = new Dompdf(options);
            dompdf.loadHtml(html);
            Object row = certificateTable.getRecord(id);
            String orientation = ((RowType) row).orientation.equals("p") ? "portrait" : "landscape";

            dompdf.setPaper("A4", orientation);
            dompdf.render();
            dompdf.stream(safeUrl(((RowType) row).name) + ".pdf");

            System.exit(0);
        } else {
            Object row = certificateTable.getRecord(id);
            String fileName = safeUrl(((RowType) row).name) + ".pdf";
            String orientation = ((RowType) row).orientation.equals("p") ? "portrait" : "landscape";

            PdfWrapper pdf = App.make("snappy.pdf.wrapper");
            pdf.loadHTML(html)
               .setPaper("a4")
               .setOrientation(orientation)
               .setOption("margin-bottom", 0)
               .setOption("margin-top", 0)
               .setOption("margin-right", 0)
               .setOption("margin-left", 0)
               .setOption("page-width", 162)
               .setOption("page-height", 230)
               .setOption("disable-smart-shrinking", true);
            return pdf.download(fileName);
        }
        return ResponseEntity.ok().build();
    }

    public String getCertificateHtml(int id) {
        CertificateTable certificateTable = new CertificateTable();
        SessionLessonTable sessionLessonTable = new SessionLessonTable();
        AttendanceTable attendanceTable = new AttendanceTable();
        StudentCertificateTable studentCertificateTable = new StudentCertificateTable();
        SessionTable sessionTable = new SessionTable();
        Object row = certificateTable.getRecord(id);
        Object sessionRow = sessionTable.getRecord(((RowType) row).course_id);
        Student student = this.getApiStudent();

        Object studentCertificate = studentCertificateTable.addStudentEntry(this.getApiStudentId(), id);
        String name = student.user.name + " " + student.user.last_name;
        Map<String, String> elements = new HashMap<>();
        elements.put("student_name", name);
        elements.put("session_name", ((RowType) sessionRow).name);
        elements.put("session_start_date", showDate("d/M/Y", ((RowType) sessionRow).start_date));
        elements.put("session_end_date", showDate("d/M/Y", ((RowType) sessionRow).end_date));
        elements.put("date_generated", java.time.LocalDate.now().format(java.time.format.DateTimeFormatter.ofPattern("d/M/Y")));
        elements.put("company_name", setting("general_site_name"));
        elements.put("certificate_number", ((StudentCertificate) studentCertificate).tracking_number);

        List<Object> lessons = sessionLessonTable.getSessionRecords(((RowType) row).course_id);
        List<StudentField> fields = StudentField.get();
        for (Object lesson : lessons) {
            String date;
            if (!((RowType) row).any_session) {
                date = attendanceTable.getStudentLessonDateInSession(this.getApiStudentId(), ((RowType) lesson).lesson_id, ((RowType) row).course_id);
            } else {
                date = attendanceTable.getStudentLessonDate(this.getApiStudentId(), ((RowType) lesson).lesson_id);
            }
            if (date == null || date.isEmpty()) {
                date = "N/A";
            }
            elements.put("class_date_" + ((RowType) lesson).lesson_id + "_" + safeUrl(((RowType) lesson).name).toUpperCase(), date);
        }

        StudentFieldTable studentFieldTable = new StudentFieldTable();

        for (StudentField field : fields) {
            String fieldValue = "";
            Object fieldValueRow = studentFieldTable.getStudentFieldRecord(this.getId(), field.id);
            if (fieldValueRow != null) {
                fieldValue = ((FieldValueRowType) fieldValueRow).value; // Assuming FieldValueRowType is the type of field value row
            }
            elements.put("student_field_" + field.id + "_" + safeUrl(field.name).toUpperCase(), fieldValue);
        }

        String html = ((RowType) row).html;

        for (Map.Entry<String, String> entry : elements.entrySet()) {
            String key = entry.getKey().toUpperCase();
            html = html.replace("[" + key + "]", entry.getValue());
        }

        return html;
    }

    public boolean canDownload(int certificateId) {
        CertificateTable certificateTable = new CertificateTable();
        Object certificateRow = certificateTable.getRecord(certificateId);
        int studentId = this.getApiStudentId();
        Student student = Student.find(studentId);

        int totalAllowed = ((RowType) certificateRow).max_downloads;
        int totalDownloaded = student.studentCertificateDownloads.count();

        return !(totalDownloaded >= totalAllowed && totalAllowed > 0);
    }

    public boolean canAccessCertificate(int certificateId) {
        CertificateTable certificateTable = new CertificateTable();
        CertificateLessonTable certificateLessonTable = new CertificateLessonTable();
        CertificateTestTable certificateTestTable = new CertificateTestTable();
        CertificateAssignmentTable certificateAssignmentTable = new CertificateAssignmentTable();
        StudentSessionTable studentSessionTable = new StudentSessionTable();
        AttendanceTable attendanceTable = new AttendanceTable();
        StudentTestTable studentTestTable = new StudentTestTable();
        AssignmentSubmissionTable studentAssignmentTable = new AssignmentSubmissionTable();

        Object certificateRow = certificateTable.getRecord(certificateId);
        int studentId = this.getApiStudentId();
        if (certificateRow.enabled == null) {
            return false;
        }

        if (!studentSessionTable.enrolled(this.getApiStudentId(), ((RowType) certificateRow).course_id)) {
            return ResponseEntity.ok(Map.of("status", false, "msg", __lang("certificate-download-error")));
        }

        if (certificateLessonTable.getTotalForCertificate(certificateId) > 0) {
            List<Integer> mClasses = new ArrayList<>();
            List<Object> rowset = certificateLessonTable.getCertificateLessons(certificateId);
            for (Object row : rowset) {
                mClasses.add(((RowType) row).lesson_id);
            }

            boolean status;
            if (!((RowType) certificateRow).any_session) {
                status = attendanceTable.hasClassesInSession(studentId, ((RowType) certificateRow).course_id, mClasses);
            } else {
                status = attendanceTable.hasClasses(studentId, mClasses);
            }

            if (!status) {
                return ResponseEntity.ok(Map.of("status", false, "msg", __lang("outstanding-classes")));
            }
        }

        if (certificateTestTable.getTotalForCertificate(certificateId) > 0) {
            List<Object> rowset = certificateTestTable.getCertificateRecords(certificateId);
            for (Object row : rowset) {
                boolean passedTest = studentTestTable.passedTest(studentId, ((RowType) row).test_id);
                if (!passedTest) {
                    Test testRecord = Test.find(((RowType) row).test_id);
                    return ResponseEntity.ok(Map.of("status", false, "msg", __lang("need-take-test", Map.of("test", testRecord.name))));
                }
            }
        }

        if (certificateAssignmentTable.getTotalForCertificate(certificateId) > 0) {
            List<Object> rowset = certificateAssignmentTable.getCertificateRecords(certificateId);
            for (Object row : rowset) {
                boolean passedAssignment = studentAssignmentTable.passedAssignment(studentId, ((RowType) row).assignment_id);
                if (!passedAssignment) {
                    Assignment assignmentRecord = Assignment.find(((RowType) row).assignment_id);
                    return ResponseEntity.ok(Map.of("status", false, "msg", __lang("assignment-needed", Map.of("assignment", assignmentRecord.title))));
                }
            }
        }

        return true;
    }
}

