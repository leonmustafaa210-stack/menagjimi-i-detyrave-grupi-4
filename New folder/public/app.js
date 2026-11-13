const authPanel = document.querySelector("#auth-panel");
const workspace = document.querySelector("#workspace");
const userActions = document.querySelector("#user-actions");
const courseList = document.querySelector("#course-list");
const welcomePanel = document.querySelector("#welcome-panel");
const classView = document.querySelector("#class-view");
const courseDialog = document.querySelector("#course-dialog");
const courseForm = document.querySelector("#course-form");
const assignmentForm = document.querySelector("#assignment-form");
const assignmentList = document.querySelector("#assignment-list");
const joinClassForm = document.querySelector("#join-class-form");
const toast = document.querySelector("#toast");

// Gjendja aktuale e faqes dhe token-i që mbron kërkesat që ndryshojnë të dhënat.
let currentUser = null;
let csrfToken = "";
let selectedCourseId = null;
let toastTimer;

function showToast(message) {
  toast.textContent = message;
  toast.classList.add("visible");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove("visible"), 3800);
}

// Dërgon kërkesa JSON në PHP dhe shfaq gabimet e API-së në ndërfaqe.
async function request(path, options = {}) {
  const headers = { "Content-Type": "application/json", ...options.headers };
  if (options.method && options.method !== "GET") headers["X-CSRF-Token"] = csrfToken;

  const response = await fetch(path, { ...options, headers });
  let data;
  try {
    data = await response.json();
  } catch {
    throw new Error("Serveri ktheu një përgjigje të pavlefshme.");
  }
  if (!response.ok) throw new Error(data.error || "Ndodhi një gabim. Provo përsëri.");
  return data;
}

// Pastro hapësirën e punës dhe kthehu te formulari i hyrjes pas daljes.
function showAuth() {
  currentUser = null;
  selectedCourseId = null;
  workspace.hidden = true;
  userActions.hidden = true;
  authPanel.hidden = false;
  document.querySelector("#register-card").hidden = true;
  document.querySelector("#login-card").hidden = false;
}

// Vendos ndërfaqen sipas rolit dhe ngarkon klasat e llogarisë.
async function showWorkspace(user) {
  currentUser = user;
  authPanel.hidden = true;
  workspace.hidden = false;
  userActions.hidden = false;
  const roleName = currentUser.role === "teacher" ? "Mësues" : "Nxënës";
  document.querySelector("#user-label").textContent = `${currentUser.name} · ${roleName}`;

  const isTeacher = currentUser.role === "teacher";
  document.querySelector("#new-course-button").hidden = !isTeacher;
  document.querySelector("#new-course-link").hidden = !isTeacher;
  joinClassForm.hidden = isTeacher;
  document.querySelector("#new-assignment-button").hidden = !isTeacher;

  const welcomeHeading = document.querySelector("#welcome-heading");
  const welcomeDescription = document.querySelector("#welcome-description");
  document.querySelector("#welcome-eyebrow").textContent = isTeacher ? "MIRË SE ERDHE, MËSUES" : "MIRË SE ERDHE, NXËNËS";
  welcomeHeading.textContent = isTeacher ? "Një vend për klasën tënde." : "Mësimi yt, në një vend.";
  welcomeDescription.textContent = isTeacher
    ? "Krijo klasën dhe ndaj kodin që nxënësit të bashkohen."
    : "Bashkohu me klasën tënde me kod dhe gjej detyrat për t'i dorëzuar.";
  document.querySelector("#welcome-create-button").hidden = !isTeacher;
  document.querySelector("#welcome-join-button").hidden = isTeacher;
  await loadCourses(null);
}

// Ndërton butonin e klasës si DOM të sigurt dhe e lidh me zgjedhjen e klasës.
function createCourseButton(course, selected) {
  const button = document.createElement("button");
  button.type = "button";
  button.className = `course-item${selected ? " active" : ""}`;
  button.setAttribute("aria-current", selected ? "page" : "false");

  const icon = document.createElement("span");
  icon.className = "course-icon";
  icon.setAttribute("aria-hidden", "true");
  icon.textContent = course.name.trim().charAt(0).toLocaleUpperCase("sq");

  const meta = document.createElement("span");
  meta.className = "course-meta";
  const name = document.createElement("strong");
  name.textContent = course.name;
  const summary = document.createElement("small");
  summary.textContent = `${course.teacher} · ${course.assignment_count} detyra`;
  meta.append(name, summary);
  button.append(icon, meta);
  button.addEventListener("click", () => selectCourse(course.id));
  return button;
}

// Merr klasat e përdoruesit, përditëson navigimin dhe hap klasën e zgjedhur.
async function loadCourses(preferredId = selectedCourseId) {
  const courses = await request("/api/courses");
  courseList.replaceChildren();
  const selectedCourse = courses.find((course) => Number(course.id) === Number(preferredId))
    || courses[0]
    || null;
  selectedCourseId = selectedCourse ? Number(selectedCourse.id) : null;

  for (const course of courses) {
    courseList.append(createCourseButton(course, Number(course.id) === selectedCourseId));
  }

  welcomePanel.hidden = Boolean(selectedCourse);
  classView.hidden = !selectedCourse;
  if (selectedCourse) {
    await loadCourse(selectedCourse.id);
  } else {
    assignmentList.replaceChildren();
    assignmentForm.hidden = true;
  }
}

// Hap një klasë tjetër duke ruajtur trajtimin e gabimeve të ngarkimit.
async function selectCourse(courseId) {
  try {
    selectedCourseId = Number(courseId);
    await loadCourses(selectedCourseId);
  } catch (error) {
    showToast(error.message);
  }
}

// Shfaq tekst të zakonshëm kur lista nuk ka përmbajtje.
function appendEmptyState(container, message) {
  const empty = document.createElement("div");
  empty.className = "empty-state";
  empty.textContent = message;
  container.append(empty);
}

// Shfaq datën e afatit sipas gjuhës shqipe pa ndërruar ditën mes zonave kohore.
function formatDueDate(value) {
  if (!value) return "Pa afat të caktuar";
  return new Intl.DateTimeFormat("sq-AL", {
    day: "numeric",
    month: "long",
    year: "numeric",
    timeZone: "UTC",
  }).format(new Date(`${value}T00:00:00Z`));
}

// Ndërton panelin ku mësuesi ngarkon dhe lexon përgjigjet e dorëzuara.
function createSubmissionPanel(assignment) {
  const details = document.createElement("details");
  details.className = "submission-panel";

  const summary = document.createElement("summary");
  const count = Number(assignment.submission_count);
  summary.textContent = `Shiko dorëzimet (${count})`;

  const results = document.createElement("div");
  results.className = "submission-results";
  appendEmptyState(results, "Ngarko dorëzimet e kësaj detyre.");
  details.append(summary, results);

  details.addEventListener("toggle", async () => {
    if (!details.open || details.dataset.loaded === "true") return;
    results.replaceChildren();
    appendEmptyState(results, "Duke ngarkuar dorëzimet...");
    try {
      const { submissions } = await request(`/api/assignments/${assignment.id}/submissions`);
      results.replaceChildren();
      if (submissions.length === 0) {
        appendEmptyState(results, "Ende nuk është dorëzuar asnjë përgjigje.");
      } else {
        for (const submission of submissions) {
          const item = document.createElement("article");
          item.className = "submission-item";
          const studentName = document.createElement("strong");
          studentName.textContent = submission.student_name;
          const studentEmail = document.createElement("span");
          studentEmail.textContent = submission.student_email;
          const answer = document.createElement("p");
          answer.textContent = submission.body;
          const timestamp = document.createElement("small");
          timestamp.textContent = `Dorëzuar: ${new Intl.DateTimeFormat("sq-AL", {
            dateStyle: "medium",
            timeStyle: "short",
          }).format(new Date(`${submission.submitted_at.replace(" ", "T")}Z`))}`;
          item.append(studentName, studentEmail, answer, timestamp);
          results.append(item);
        }
      }
      details.dataset.loaded = "true";
    } catch (error) {
      results.replaceChildren();
      appendEmptyState(results, error.message);
    }
  });

  return details;
}

// Krijon formularin e nxënësit për dorëzimin ose përditësimin e përgjigjes.
function createStudentSubmissionForm(assignment) {
  const form = document.createElement("form");
  form.className = "answer-form";

  const label = document.createElement("label");
  const textareaId = `answer-${assignment.id}`;
  label.htmlFor = textareaId;
  label.textContent = assignment.submission_body ? "Përgjigjja jote" : "Shkruaj përgjigjen tënde";

  const textarea = document.createElement("textarea");
  textarea.id = textareaId;
  textarea.name = "body";
  textarea.maxLength = 10000;
  textarea.rows = 4;
  textarea.required = true;
  textarea.placeholder = "Shkruaj dhe dorëzo punën këtu...";
  textarea.value = assignment.submission_body || "";

  const actions = document.createElement("div");
  actions.className = "answer-actions";
  const status = document.createElement("span");
  status.className = assignment.submission_body ? "submission-status submitted" : "submission-status";
  status.textContent = assignment.submission_body ? "✓ E dorëzuar" : "Ende pa dorëzuar";

  const submit = document.createElement("button");
  submit.type = "submit";
  submit.className = "primary-button";
  submit.textContent = assignment.submission_body ? "Përditëso përgjigjen" : "Dorëzo detyrën";
  actions.append(status, submit);
  form.append(label, textarea, actions);

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    submit.disabled = true;
    try {
      const { submission } = await request(`/api/assignments/${assignment.id}/submissions`, {
        method: "POST",
        body: JSON.stringify({ body: textarea.value }),
      });
      assignment.submission_body = submission.body;
      assignment.submitted_at = submission.submitted_at;
      status.className = "submission-status submitted";
      status.textContent = "✓ E dorëzuar";
      label.textContent = "Përgjigjja jote";
      submit.textContent = "Përditëso përgjigjen";
      showToast("Detyra u dorëzua me sukses.");
    } catch (error) {
      showToast(error.message);
    } finally {
      submit.disabled = false;
    }
  });

  if (assignment.submitted_at) {
    const submittedAt = document.createElement("small");
    submittedAt.className = "submission-time";
    submittedAt.textContent = `Dorëzuar: ${new Intl.DateTimeFormat("sq-AL", {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(new Date(`${assignment.submitted_at.replace(" ", "T")}Z`))}`;
    form.append(submittedAt);
  }

  return form;
}

// Vizaton kartën e detyrës me kontrollet përkatëse të mësuesit ose nxënësit.
function createAssignmentCard(assignment) {
  const card = document.createElement("article");
  card.className = "assignment-card";

  const icon = document.createElement("span");
  icon.className = "assignment-icon";
  icon.setAttribute("aria-hidden", "true");
  icon.textContent = "↗";

  const copy = document.createElement("div");
  copy.className = "assignment-copy";
  const title = document.createElement("h4");
  title.textContent = assignment.title;
  copy.append(title);
  if (assignment.details) {
    const details = document.createElement("p");
    details.textContent = assignment.details;
    copy.append(details);
  }
  const dueDate = document.createElement("p");
  dueDate.className = "due-date";
  dueDate.textContent = `Afati: ${formatDueDate(assignment.due_date)}`;
  copy.append(dueDate);

  card.append(icon, copy);
  if (currentUser.role === "teacher") {
    const count = Number(assignment.submission_count);
    const summary = document.createElement("span");
    summary.className = "submission-count";
    summary.textContent = `${count} dorëzime`;
    card.append(summary, createSubmissionPanel(assignment));
  } else {
    card.classList.add("student-assignment-card");
    card.append(createStudentSubmissionForm(assignment));
  }

  return card;
}

// Ngarkon hollësitë e klasës dhe detyrat e dukshme për përdoruesin aktual.
async function loadCourse(courseId) {
  const { course, assignments } = await request(`/api/courses/${courseId}`);
  document.querySelector("#class-name").textContent = course.name;
  document.querySelector("#class-teacher").textContent = currentUser.role === "teacher"
    ? `${course.teacher} · ${course.student_count} nxënës`
    : `Mësuesi: ${course.teacher}`;

  const codeBox = document.querySelector("#class-code-box");
  codeBox.hidden = currentUser.role !== "teacher";
  document.querySelector("#class-code").textContent = course.class_code || "";

  const description = document.querySelector("#class-description");
  description.textContent = course.description;
  description.hidden = !course.description;
  assignmentList.replaceChildren();

  if (assignments.length === 0) {
    appendEmptyState(
      assignmentList,
      currentUser.role === "teacher"
        ? "Ende nuk ka detyra. Shto detyrën e parë për këtë klasë."
        : "Mësuesi ende nuk ka shtuar detyra në këtë klasë."
    );
    return;
  }

  for (const assignment of assignments) {
    assignmentList.append(createAssignmentCard(assignment));
  }
}

// Hap dialogun e krijimit të klasës për mësuesin.
function openCourseDialog() {
  courseDialog.showModal();
  document.querySelector("#course-name").focus();
}

document.querySelectorAll("#new-course-button, #new-course-link, #welcome-create-button")
  .forEach((button) => button.addEventListener("click", openCourseDialog));

// Butoni në mesazh e çon nxënësin te fusha e kodit të klasës.
document.querySelector("#welcome-join-button").addEventListener("click", () => {
  document.querySelector("#join-class-code").focus();
  joinClassForm.scrollIntoView({ behavior: "smooth", block: "center" });
});

document.querySelector("#close-course-dialog").addEventListener("click", () => courseDialog.close());
document.querySelector("#cancel-course-dialog").addEventListener("click", () => courseDialog.close());
courseDialog.addEventListener("click", (event) => {
  if (event.target === courseDialog) courseDialog.close();
});

document.querySelector("#show-register").addEventListener("click", () => {
  document.querySelector("#login-card").hidden = true;
  document.querySelector("#register-card").hidden = false;
  document.querySelector("#register-name").focus();
});

document.querySelector("#show-login").addEventListener("click", () => {
  document.querySelector("#register-card").hidden = true;
  document.querySelector("#login-card").hidden = false;
  document.querySelector("#login-email").focus();
});

// Përdor të njëjtin mekanizëm JSON për regjistrimin dhe hyrjen në llogari.
async function submitAuthentication(form, endpoint) {
  const button = form.querySelector('button[type="submit"]');
  button.disabled = true;
  try {
    const payload = Object.fromEntries(new FormData(form));
    const result = await request(endpoint, {
      method: "POST",
      body: JSON.stringify(payload),
    });
    csrfToken = result.csrf_token;
    form.reset();
    await showWorkspace(result.user);
    showToast(endpoint === "/api/register" ? "Llogaria u krijua me sukses." : "Hyrja u krye me sukses.");
  } catch (error) {
    showToast(error.message);
  } finally {
    button.disabled = false;
  }
}

// Regjistron veprimet e hyrjes, regjistrimit dhe daljes.
document.querySelector("#login-form").addEventListener("submit", (event) => {
  event.preventDefault();
  submitAuthentication(event.currentTarget, "/api/login");
});

document.querySelector("#register-form").addEventListener("submit", (event) => {
  event.preventDefault();
  submitAuthentication(event.currentTarget, "/api/register");
});

document.querySelector("#logout-button").addEventListener("click", async (event) => {
  const button = event.currentTarget;
  button.disabled = true;
  try {
    const result = await request("/api/logout", { method: "POST", body: "{}" });
    csrfToken = result.csrf_token;
    showAuth();
    showToast("U çkyçe me sukses.");
  } catch (error) {
    showToast(error.message);
  } finally {
    button.disabled = false;
  }
});

// Dërgon kodin e ftesës për të regjistruar nxënësin në një klasë.
joinClassForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const button = joinClassForm.querySelector('button[type="submit"]');
  button.disabled = true;
  try {
    const result = await request("/api/courses/join", {
      method: "POST",
      body: JSON.stringify({
        class_code: document.querySelector("#join-class-code").value.trim().toUpperCase(),
      }),
    });
    joinClassForm.reset();
    await loadCourses(result.id);
    showToast("U bashkove me klasën me sukses.");
  } catch (error) {
    showToast(error.message);
  } finally {
    button.disabled = false;
  }
});

// Ruaj klasën e mësuesit dhe pastaj shfaq kodin e saj të ftesës.
courseForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const button = courseForm.querySelector('button[type="submit"]');
  button.disabled = true;
  try {
    const course = await request("/api/courses", {
      method: "POST",
      body: JSON.stringify(Object.fromEntries(new FormData(courseForm))),
    });
    courseForm.reset();
    courseDialog.close();
    await loadCourses(course.id);
    showToast("Klasa u krijua. Ndaje kodin me nxënësit.");
  } catch (error) {
    showToast(error.message);
  } finally {
    button.disabled = false;
  }
});

document.querySelector("#new-assignment-button").addEventListener("click", () => {
  assignmentForm.hidden = !assignmentForm.hidden;
  if (!assignmentForm.hidden) document.querySelector("#assignment-title").focus();
});

document.querySelectorAll("[data-close-form]").forEach((button) => {
  button.addEventListener("click", () => {
    assignmentForm.reset();
    assignmentForm.hidden = true;
  });
});

// Ruaj një detyrë në klasën e zgjedhur dhe rifresko listën pas suksesit.
assignmentForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (!selectedCourseId) return;
  const button = assignmentForm.querySelector('button[type="submit"]');
  button.disabled = true;
  try {
    await request(`/api/courses/${selectedCourseId}/assignments`, {
      method: "POST",
      body: JSON.stringify(Object.fromEntries(new FormData(assignmentForm))),
    });
    assignmentForm.reset();
    assignmentForm.hidden = true;
    await loadCourses(selectedCourseId);
    showToast("Detyra u shtua me sukses.");
  } catch (error) {
    showToast(error.message);
  } finally {
    button.disabled = false;
  }
});

// Lexon sesionin e ruajtur dhe vendos faqen fillestare pas hapjes.
async function initialize() {
  try {
    const auth = await request("/api/auth");
    csrfToken = auth.csrf_token;
    if (auth.user) {
      await showWorkspace(auth.user);
    } else {
      showAuth();
    }
  } catch (error) {
    showAuth();
    showToast(error.message);
  }
}

initialize();
