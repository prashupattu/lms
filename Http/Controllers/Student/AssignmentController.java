import java.io.File;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.StandardCopyOption;
import java.time.LocalDateTime;
import java.util.HashMap;
import java.util.Map;

public class AssignmentController extends Controller {

    private String uploadDir;

    public AssignmentController() {
        String user = "";
        if (System.getProperty("USER_ID") != null) {
            user = "/" + System.getProperty("USER_ID");
        }
        this.uploadDir = "usermedia" + user + "/student_uploads/" + LocalDateTime.now().getYear() + "_" + (LocalDateTime.now().getMonthValue());
    }

    private void makeUploadDir() {
        File path = new File(uploadDir);
        if (!path.exists()) {
            path.mkdirs();
        }
    }

    public Map<String, Object> index(Request request) {
        int studentId = getId();
        StudentSessionTable studentSessionTable = new StudentSessionTable();
        AssignmentSubmissionTable submissionTable = new AssignmentSubmissionTable();

        Paginator paginator = studentSessionTable.getAssignments(studentId);
        paginator.setCurrentPageNumber(Integer.parseInt(request.getParameter("page", "1")));
        paginator.setItemCountPerPage(30);

        Map<String, Object> model = new HashMap<>();
        model.put("pageTitle", __lang("Homework"));
        model.put("paginator", paginator);
        model.put("submissionTable", submissionTable);
        model.put("total", studentSessionTable.getTotalAssignments(studentId));
        return model;
    }

    private void validateAssignment(int id) {
        AssignmentTable assignmentTable = new AssignmentTable();
        StudentSessionTable studentSessionTable = new StudentSessionTable();
        AssignmentRow assignmentRow = assignmentTable.getRecord(id);

        if (assignmentRow.allowLate != 1 && assignmentRow.dueDate.isBefore(LocalDateTime.now())) {
            flashMessage(__lang("ass-past-due-date"));
            back();
        }

        if (!studentSessionTable.enrolled(getId(), assignmentRow.courseId)) {
            flashMessage(__lang("you-not-enrolled"));
            back();
        }
    }

    public Map<String, Object> submit(Request request, int id) {
        String courseUrl = session.get("course");

        Map<String, Object> output = new HashMap<>();
        AssignmentTable assignmentTable = new AssignmentTable();
        AssignmentSubmissionTable assignmentSubmissionTable = new AssignmentSubmissionTable();
        AssignmentRow assignmentRow = assignmentTable.getRecord(id);
        BaseForm form = getForm(id);

        validateAssignment(id);

        // Check if student has submitted assignment
        if (assignmentSubmissionTable.hasSubmission(getId(), id)) {
            SubmissionRow submissionRow = assignmentSubmissionTable.getAssignment(id, getId());
            if (canEdit(submissionRow.id)) {
                flashMessage(__lang("already-submitted-msg"));
                return redirect("student.assignment.edit", submissionRow.id);
            } else {
                flashMessage(__lang("ass-no-edit-msg"));
                return back();
            }
        }

        if (request.isMethod("post")) {
            Map<String, Object> formData = request.getAll();

            form.setData(mergeMaps(formData, request.getFiles()));
            if (form.isValid()) {
                Map<String, Object> data = form.getData();

                // Handle file upload
                if (assignmentRow.type.equals("f") || assignmentRow.type.equals("b")) {
                    String file = (String) data.get("file_path.name");
                    String newPath = uploadDir + "/" + System.currentTimeMillis() + getId() + "_" + sanitize(file);
                    makeUploadDir();
                    try {
                        Files.move(new File((String) data.get("file_path.tmp_name")).toPath(), new File(newPath).toPath(), StandardCopyOption.REPLACE_EXISTING);
                        new File(newPath).setReadable(true, false);
                        new File(newPath).setWritable(false, false);
                    } catch (IOException e) {
                        e.printStackTrace();
                    }

                    data.put("file_path", newPath);
                }
                String content = data.containsKey("content") ? saveInlineImages((String) data.get("content"), getBaseUrl()) : "";
                data.put("content", clean(content));
                data.put("student_id", getId());
                data.put("assignment_id", id);
                data.put("editable", 1);

                int aid = assignmentSubmissionTable.addRecord(data);
                if (data.get("submitted").equals(1) && aid != 0) {
                    Student student = getStudent();
                    String message = student.user.name + " " + student.user.lastName + " " + __lang("just-submitted-msg") + " \"" + assignmentRow.title + "\"";
                    notifyAdmin(assignmentRow.adminId, __lang("New homework submission"), message);
                }
                flashMessage(__lang("you-successfully-submitted"));
                if (courseUrl != null) {
                    session.remove("course");
                    return redirect(courseUrl);
                }

                return redirect("student.assignment.submissions");
            } else {
                output.put("message", getFormErrors(form));
            }
        }

        Map<String, Object> model = new HashMap<>(output);
        model.put("pageTitle", __lang("Submit Homework") + ": " + assignmentRow.title);
        model.put("form", form);
        model.put("row", assignmentRow);
        return model;
    }

    public Map<String, Object> submissions(Request request) {
        int studentId = getId();
        AssignmentSubmissionTable assignmentSubmissionsTable = new AssignmentSubmissionTable();

        Paginator paginator = assignmentSubmissionsTable.getStudentPaginatedRecords(true, studentId);
        paginator.setCurrentPageNumber(Integer.parseInt(request.getParameter("page", "1")));
        paginator.setItemCountPerPage(30);

        Map<String, Object> model = new HashMap<>();
        model.put("pageTitle", __lang("My Homework Submissions"));
        model.put("paginator", paginator);
        return model;
    }

    private boolean canEdit(int id) {
        AssignmentSubmissionTable assignmentSubmissionTable = new AssignmentSubmissionTable();
        AssignmentTable assignmentTable = new AssignmentTable();

        // Get assignment
        SubmissionRow row = assignmentSubmissionTable.getRecord(id);
        AssignmentRow assignmentRow = assignmentTable.getRecord(row.assignmentId);
        LocalDateTime time = LocalDateTime.now();
        return !(row.editable == null || (assignmentRow.allowLate != 1 && assignmentRow.dueDate.isBefore(time)));
    }

    public Map<String, Object> edit(Request request, int id) {
        AssignmentSubmissionTable assignmentSubmissionTable = new AssignmentSubmissionTable();
        AssignmentTable assignmentTable = new AssignmentTable();

        // Get assignment
        SubmissionRow row = assignmentSubmissionTable.getRecord(id);
        AssignmentRow assignmentRow = assignmentTable.getRecord(row.assignmentId);
        if (!canEdit(id)) {
            flashMessage(__lang("sorry-no-edit"));
            return back();
        }

        BaseForm form = getForm(assignmentRow.id, false);

        if (form.has("file_path")) {
            form.get("file_path").setAttribute("required", "");
        }

        if (request.isMethod("post")) {
            Map<String, Object> formData = request.getAll();

            form.setData(mergeMaps(formData, request.getFiles()));

            if (form.isValid()) {
                Map<String, Object> data = form.getData();

                // Handle file upload
                if ((assignmentRow.type.equals("f") || assignmentRow.type.equals("b")) && data.containsKey("file_path.name")) {
                    // Remove old file
                    new File(row.filePath).delete();
                    String file = (String) data.get("file_path.name");
                    String newPath = uploadDir + "/" + System.currentTimeMillis() + getId() + "_" + sanitize(file);
                    makeUploadDir();
                    try {
                        Files.move(new File((String) data.get("file_path.tmp_name")).toPath(), new File(newPath).toPath(), StandardCopyOption.REPLACE_EXISTING);
                        new File(newPath).setReadable(true, false);
                        new File(newPath).setWritable(false, false);
                    } catch (IOException e) {
                        e.printStackTrace();
                    }

                    data.put("file_path", newPath);
                } else {
                    data.remove("file_path");
                }

                assignmentSubmissionTable.update(data, id);
                flashMessage(__lang("you-successfully-edited"));
                return redirect("student.assignment.submissions");
            } else {
                String message = getFormErrors(form);
                flashMessage(message);
            }
        } else {
            Map<String, Object> data = getObjectProperties(row);
            form.setData(data);
        }
        String pageTitle = __lang("Edit Assignment") + ": " + assignmentRow.title;

        Map<String, Object> model = new HashMap<>();
        model.put("pageTitle", pageTitle);
        model.put("assignmentRow", assignmentRow);
        model.put("form", form);
        if (row.filePath != null) {
            model.put("file", new File(row.filePath).getName());
        }
        return model;
    }

    public Map<String, Object> delete(Request request, int id) {
        AssignmentSubmissionTable assignmentSubmissionTable = new AssignmentSubmissionTable();
        AssignmentTable assignmentTable = new AssignmentTable();

        // Get assignment
        SubmissionRow row = assignmentSubmissionTable.getRecord(id);
        AssignmentRow assignmentRow = assignmentTable.getRecord(row.assignmentId);
        if (row.editable == null || (assignmentRow.allowLate != 1 && assignmentRow.dueDate.isBefore(LocalDateTime.now()))) {
            flashMessage(__lang("sorry-no-delete"));
            return back();
        }

        validateOwner(row);

        if (row.filePath != null) {
            new File(row.filePath).delete();
        }

        assignmentSubmissionTable.deleteRecord(id);
        flashMessage(__lang("Assignment deleted"));
        return back();
    }

    public Map<String, Object> view(Request request, int id) {
        AssignmentSubmissionTable assignmentSubmissionTable = new AssignmentSubmissionTable();
        SubmissionRow row = assignmentSubmissionTable.getSubmission(id);
        Map<String, Object> model = new HashMap<>();
        model.put("row", row);
        return model;
    }

    private BaseForm getForm(int id, boolean fileRequired) {
        AssignmentTable assignmentTable = new AssignmentTable();
        AssignmentRow assignmentRow = assignmentTable.getRecord(id);
        BaseForm form = new BaseForm();

        if (assignmentRow.type.equals("t") || assignmentRow.type.equals("b")) {
            form.createTextArea("content", "Your Answer", true);
            form.get("content").setAttribute("class", "summernote form-control");
        }

        if (assignmentRow.type.equals("f") || assignmentRow.type.equals("b")) {
            File file = new File("file_path");
            file.setLabel(__lang("Your File"))
                .setAttribute("id", "file_path")
                .setAttribute("required", "required");
            form.add(file);
        }

        form.createSelect("submitted", "Status", new HashMap<String, String>() {{
            put("1", __lang("Submitted"));
            put("0", __lang("Draft"));
        }}, true, false);
        form.createTextArea("student_comment", "Additional Comments (optional)", false);

        form.setInputFilter(getFilter(id, fileRequired));
        return form;
    }

    private InputFilter getFilter(int id, boolean fileRequired) {
        AssignmentTable assignmentTable = new AssignmentTable();
        AssignmentRow assignmentRow = assignmentTable.getRecord(id);
        InputFilter filter = new InputFilter();

        if (assignmentRow.type.equals("t") || assignmentRow.type.equals("b")) {
            filter.add(new Input("content", true, new NotEmptyValidator()));
        }

        if (assignmentRow.type.equals("f") || assignmentRow.type.equals("b")) {
            Input input = new Input("file_path");
            input.setRequired(fileRequired);
            input.getValidatorChain()
                .attach(new SizeValidator(5000000))
                .attach(new ExtensionValidator("jpg,mp4,mp3,avi,xls,7z,mdb,mdbx,csv,xlsx,txt,zip,doc,docx,pptx,pdf,ppt,png,gif,jpeg"));

            filter.add(input);
        }

        filter.add(new Input("submitted", false));
        filter.add(new Input("student_comment", false));

        return filter;
    }
}

