<script setup>
import {
  ref,
  computed,
  nextTick,
  onMounted,
  onBeforeUnmount,
  watch,
} from "vue";
import axios from "axios";
import Swal from "sweetalert2";
import { usePersonnelStore } from "./stores/personnel";
const store = usePersonnelStore();
const logoAsset = "/images/uch-logo.png";
const nav = ref("Overview"),
  profileTab = ref(0),
  search = ref(""),
  showInvite = ref(false),
  showOptions = ref(false);
const showSignup = ref(false),
  signupStep = ref(0),
  signupEmail = ref(""),
  signupPassword = ref(""),
  signupConfirm = ref(""),
  signupCode = ref(""),
  signupError = ref(""),
  signupBusy = ref(false);
const showAuthPassword = ref(false),
  showSignupPassword = ref(false),
  showSignupConfirm = ref(false),
  showAdminPassword = ref(false);
const chatPeople = ref([]),
  chatMessages = ref([]),
  chatSelectedPerson = ref(null),
  chatSearch = ref(""),
  chatDraft = ref(""),
  chatBusy = ref(false),
  chatSending = ref(false),
  chatError = ref(""),
  chatRealtimeStatus = ref("connecting");
let chatRefreshTimer = null,
  activeChatChannel = null;
watch(showInvite, (value) => {
  if (value) {
    showSignup.value = false;
    showOptions.value = false;
  }
});
watch(showSignup, (value) => {
  if (value) {
    showInvite.value = false;
    showOptions.value = false;
  }
});
watch(showOptions, (value) => {
  if (value) {
    showInvite.value = false;
    showSignup.value = false;
  }
});
const profileData = ref({}),
  profileStatus = ref("incomplete");
const clearingProfileSection = ref(false);
const educationDrafts = ref([{}]);
const olevelDrafts = ref([]);
const isJuniorStaff = computed(() =>
  (Array.isArray(profileData.value.job_categories)
    ? profileData.value.job_categories
    : []
  ).some((category) =>
    String(category).trim().toLowerCase().startsWith("junior staff")
  )
);
const nursingSchoolProfile = computed(() =>
  educationDrafts.value.some((row) =>
    [row.institution, row.course, row.qualification].some((value) =>
      /nurs/i.test(value || "")
    )
  )
);
const canAddOlevelSitting = computed(() =>
  isJuniorStaff.value && nursingSchoolProfile.value
    ? olevelDrafts.value.length === 0
    : olevelDrafts.value.length < 10
);
const hasCompleteOlevelResult = computed(() =>
  olevelDrafts.value.some(
    (exam) =>
      String(exam.exam_year || "").trim() &&
      String(exam.exam_type || "").trim() &&
      String(exam.sitting || "").trim() &&
      (exam.subjects || []).some(
        (subject) =>
          String(subject.subject || "").trim() &&
          String(subject.grade || "").trim()
      )
  )
);
const printableSections = computed(() =>
  sections
    .map((section) => ({
      ...section,
      rows:
        section.title === "Education"
          ? [
              ...educationDrafts.value
                .filter((row) => Object.values(row || {}).some(Boolean))
                .map((row, i) => ({
                  label: `Education ${String(i + 1).padStart(2, "0")}`,
                  value:
                    [
                      row.qualification,
                      row.course,
                      row.institution,
                      row.year ? `Graduated ${row.year}` : "",
                    ]
                      .filter(Boolean)
                      .join(" · ") || "Not provided",
                })),
              ...olevelDrafts.value
                .filter(
                  (row) =>
                    row.exam_type ||
                    row.exam_year ||
                    (row.subjects || []).some(
                      (subject) => subject.subject || subject.grade
                    )
                )
                .map((exam, i) => ({
                  label: `O’Level · ${exam.exam_type || "Exam"} · ${
                    exam.sitting || `Sitting ${i + 1}`
                  }`,
                  value: [
                    exam.exam_year ? `Year ${exam.exam_year}` : "",
                    (exam.subjects || [])
                      .filter((subject) => subject.subject || subject.grade)
                      .map(
                        (subject) =>
                          `${subject.subject || "Subject"}: ${
                            subject.grade || "—"
                          }`
                      )
                      .join(", "),
                  ]
                    .filter(Boolean)
                    .join(" · "),
                })),
            ]
          : section.title === "Job categorization"
          ? [
              {
                label: "Job categorization",
                value:
                  (profileData.value.job_categories || []).join(", ") ||
                  "Not provided",
              },
            ]
          : section.fields
              .filter(
                (field) =>
                  !field.toLowerCase().includes("upload") &&
                  !field.toLowerCase().includes("photograph")
              )
              .map((field) => ({
                label: field,
                value: profileData.value[field] || "Not provided",
              })),
    }))
    .filter((section) => section.rows.length)
);
const directoryFilters = ref({
    name: "",
    email: "",
    ippis_no: "",
    file_no: "",
  }),
  directoryRows = ref([]),
  directoryBusy = ref(false),
  directoryPage = ref(1),
  directoryTotal = ref(0);
const adminEmail = ref(""),
  adminPassword = ref(""),
  loginError = ref(""),
  loginBusy = ref(false),
  inviteEmail = ref(""),
  invitationPageBusy = ref(false),
  invitationPageMessage = ref("");
const adminPermissionOptions = [
  {
    key: "view_dashboard",
    label: "View dashboard",
    description: "See personnel totals and recent activity.",
  },
  {
    key: "view_personnel",
    label: "View personnel records",
    description: "Search and open personnel profiles.",
  },
  {
    key: "review_personnel",
    label: "Review profiles",
    description: "Set profile status after HR review.",
  },
  {
    key: "export_personnel",
    label: "Export to Excel",
    description: "Download personnel data in Excel format.",
  },
  {
    key: "manage_invitations",
    label: "Manage invitations",
    description: "Approve emails individually or by CSV import.",
  },
  {
    key: "manage_lookups",
    label: "Manage form options",
    description: "Add, edit, deactivate and import dropdown options.",
  },
];
const newAdmin = ref({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    permissions: adminPermissionOptions.map((item) => item.key),
  }),
  adminCreateBusy = ref(false),
  adminCreateMessage = ref(""),
  adminAccounts = ref([]),
  adminAccountsMessage = ref(""),
  adminPermissionsBusy = ref(false);
const authView = ref("signin"),
  authEmail = ref(""),
  authPassword = ref(""),
  authError = ref(""),
  authSuccess = ref(""),
  authBusy = ref(false),
  resetStep = ref("email"),
  resetEmail = ref(""),
  resetCode = ref(""),
  resetPassword = ref(""),
  resetPasswordConfirm = ref(""),
  showResetPassword = ref(false),
  showResetPasswordConfirm = ref(false),
  resetError = ref(""),
  resetNotice = ref("");
const resetPasswordsMatch = computed(
  () =>
    resetPassword.value.length > 0 &&
    resetPassword.value === resetPasswordConfirm.value
);
const resetPasswordValid = computed(() =>
  /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,200}$/.test(
    resetPassword.value
  )
);
const sections = [
  {
    title: "Appointment",
    icon: "mdi-briefcase-outline",
    fields: [
      "File number",
      "IPPIS number",
      "Date of first appointment",
      "PFA name",
      "Pension PIN",
      "Salary structure",
      "Special categorization",
      "Level",
      "Step",
      "Department",
      "Designation",
    ],
  },
  {
    title: "Personal details",
    icon: "mdi-account-outline",
    fields: [
      "Title",
      "Surname",
      "First name",
      "Other names",
      "Maiden name",
      "Gender",
      "Date of birth",
      "Marital status",
      "Religion",
    ],
  },
  {
    title: "Contact & address",
    icon: "mdi-map-marker-outline",
    fields: [
      "Email",
      "Phone number",
      "Residential address",
      "City / town",
      "State of residence",
      "LGA of residence",
      "Permanent address",
      "State of origin",
      "LGA of origin",
      "Senatorial district",
    ],
  },
  {
    title: "Next of kin",
    icon: "mdi-account-heart-outline",
    fields: [
      "Next of kin surname",
      "Next of kin first name",
      "Next of kin relationship",
      "Next of kin email",
      "Next of kin phone number",
    ],
  },
  {
    title: "Job categorization",
    icon: "mdi-tag-multiple-outline",
    fields: ["Select every category that applies"],
  },
  {
    title: "Education",
    icon: "mdi-school-outline",
    fields: ["Institution", "Course", "Qualification", "Year of graduation"],
  },
  {
    title: "Bank details",
    icon: "mdi-bank-outline",
    fields: ["Bank name", "Account name", "Account number"],
  },
];
const configurableCategories = [
  "title",
  "gender",
  "marital_status",
  "religion",
  "salary_structure",
  "special_categorization",
  "level",
  "step",
  "department",
  "designation",
  "qualification",
  "pfa_name",
  "state",
  "local_government",
  "senatorial_district",
  "job_categorization",
  "bank_name",
  "next_of_kin_relationship",
];
const stats = ref([]),
  people = ref([]),
  dashboardTotal = ref(0),
  pendingReview = ref(0),
  incompleteCount = ref(0),
  completeCount = ref(0),
  dashboardDepartments = ref(0),
  adminLookups = ref([]),
  optionCategory = ref(""),
  optionLabel = ref(""),
  optionParent = ref(""),
  editingLookupId = ref(null),
  lookupSearch = ref(""),
  lookupCategoryFilter = ref(""),
  expandedLookupCategory = ref(""),
  bulkFile = ref(null),
  bulkResult = ref("");
const profileCompletionPercent = computed(() =>
  dashboardTotal.value
    ? Math.round((completeCount.value * 100) / dashboardTotal.value)
    : 0
);
const profileRingStyle = computed(() => ({
  background: `conic-gradient(#087e4b 0 ${
    profileCompletionPercent.value
  }%, #e7a846 ${profileCompletionPercent.value}% ${
    dashboardTotal.value
      ? Math.min(
          100,
          profileCompletionPercent.value +
            Math.round((pendingReview.value * 100) / dashboardTotal.value)
        )
      : profileCompletionPercent.value
  }%, #e5e9e6 0)`,
}));
const lookupImportResult = ref("");
const addingDropdownField = ref(""),
  addingEducationIndex = ref(null),
  newDropdownOption = ref(""),
  dropdownOptionBusy = ref(false);
const lookupGroups = computed(() => {
  const term = lookupSearch.value.trim().toLowerCase();
  const groups = {};
  for (const category of configurableCategories) {
    if (lookupCategoryFilter.value && lookupCategoryFilter.value !== category)
      continue;
    const rows = adminLookups.value.filter(
      (row) => row.active && row.category === category
    );
    const categoryMatches = categoryTitle(category)
      .toLowerCase()
      .includes(term);
    const matchingRows = rows.filter((row) =>
      row.label.toLowerCase().includes(term)
    );
    if (!term || categoryMatches || matchingRows.length)
      groups[category] = term && !categoryMatches ? matchingRows : rows;
  }
  return groups;
});
function lookupGroupIsOpen(category) {
  return (
    expandedLookupCategory.value === category ||
    lookupCategoryFilter.value === category ||
    !!lookupSearch.value.trim()
  );
}
function toggleLookupGroup(category) {
  expandedLookupCategory.value =
    expandedLookupCategory.value === category ? "" : category;
}
const displayName = computed(
  () => store.user?.name?.trim().split(/\s+/)[0] || "colleague"
);
function can(permission) {
  if (store.user?.role === "superadmin") return true;
  const permissions = store.user?.permissions || [];
  return (
    permissions.includes(permission) ||
    (permission === "view_personnel" &&
      permissions.some((item) =>
        ["review_personnel", "export_personnel"].includes(item)
      ))
  );
}
function adminLanding() {
  return adminPrimaryItems.value[0] || adminManageItems.value[0] || "Overview";
}
const adminPrimaryItems = computed(() =>
  ["admin", "superadmin"].includes(store.user?.role)
    ? [
        ...["Overview", "Personnel directory"].filter((item) =>
          can(item === "Overview" ? "view_dashboard" : "view_personnel")
        ),
        "Messages",
      ]
    : ["Profile setup", "Messages"]
);
const adminManageItems = computed(() =>
  [
    "Dropdown options",
    "Personnel invitations",
    ...(store.user?.role === "superadmin" ? ["Access & roles"] : []),
  ].filter((item) =>
    can(
      item === "Dropdown options"
        ? "manage_lookups"
        : item === "Personnel invitations"
        ? "manage_invitations"
        : "manage_admins"
    )
  )
);
const todayLabel = computed(() =>
  new Intl.DateTimeFormat("en-NG", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  })
    .format(new Date())
    .toUpperCase()
);
const filtered = computed(() =>
  people.value.filter((p) =>
    (p.name + p.role + p.dept + p.email)
      .toLowerCase()
      .includes(search.value.toLowerCase())
  )
);
const swalClasses = {
  popup: "pis-swal-popup",
  title: "pis-swal-title",
  htmlContainer: "pis-swal-copy",
  confirmButton: "pis-swal-confirm",
  cancelButton: "pis-swal-cancel",
  denyButton: "pis-swal-deny",
  input: "pis-swal-input",
  actions: "pis-swal-actions",
};
function addSwalPasswordToggle(inputOverride = null) {
  const input = inputOverride || Swal.getInput();
  if (!input) return;

  input.type = "password";
  input.autocomplete = "new-password";
  input.classList.add("pis-swal-password-input");

  const inputParent = input.parentElement;
  if (!inputParent) return;
  if (inputParent.classList.contains("pis-swal-password-wrap")) return;

  const wrapper = document.createElement("div");
  wrapper.className = "pis-swal-password-wrap";
  inputParent.insertBefore(wrapper, input);
  wrapper.appendChild(input);

  const toggle = document.createElement("button");
  toggle.type = "button";
  toggle.className = "pis-swal-password-toggle";
  toggle.setAttribute("aria-label", "Show password");
  toggle.setAttribute("aria-pressed", "false");
  toggle.innerHTML =
    '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.2 12s3.6-7 9.8-7 9.8 7 9.8 7-3.6 7-9.8 7-9.8-7-9.8-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
  toggle.addEventListener("click", () => {
    const reveal = input.type === "password";
    input.type = reveal ? "text" : "password";
    toggle.setAttribute(
      "aria-label",
      reveal ? "Hide password" : "Show password"
    );
    toggle.setAttribute("aria-pressed", String(reveal));
    toggle.innerHTML = reveal
      ? '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m3 3 18 18M10.6 5.2A10.9 10.9 0 0 1 12 5c6.2 0 9.8 7 9.8 7a16 16 0 0 1-3.2 4.1M6.2 6.2C3.6 8.1 2.2 12 2.2 12s3.6 7 9.8 7c1.2 0 2.3-.3 3.3-.7"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>'
      : '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.2 12s3.6-7 9.8-7 9.8 7 9.8 7-3.6 7-9.8 7-9.8-7-9.8-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
    input.focus();
  });
  wrapper.appendChild(toggle);
}

function notify(message, type) {
  const icon =
    type ||
    (/could not|unable|error|failed|does not have|try again|not available|invalid/i.test(
      message
    )
      ? "error"
      : "success");
  Swal.fire({
    toast: true,
    position: "top-end",
    icon,
    title: message,
    showConfirmButton: false,
    timer: 3600,
    timerProgressBar: true,
    customClass: { popup: "pis-swal-toast", title: "pis-swal-toast-title" },
  });
}
function confirmAction({
  title,
  text,
  confirmButtonText = "Continue",
  icon = "warning",
}) {
  return Swal.fire({
    icon,
    title,
    text,
    showCancelButton: true,
    confirmButtonText,
    cancelButtonText: "Cancel",
    reverseButtons: true,
    focusCancel: true,
    buttonsStyling: false,
    customClass: swalClasses,
  });
}
function promptText({
  title,
  label,
  placeholder,
  confirmButtonText = "Save",
  value = "",
}) {
  return Swal.fire({
    title,
    input: "text",
    inputLabel: label,
    inputValue: value,
    inputPlaceholder: placeholder,
    showCancelButton: true,
    confirmButtonText,
    cancelButtonText: "Cancel",
    reverseButtons: true,
    focusCancel: true,
    buttonsStyling: false,
    customClass: swalClasses,
    inputValidator: (input) =>
      input?.trim() ? undefined : "Please enter a value before continuing.",
  });
}
function humanError(error, fallback) {
  const fieldErrors = error.response?.data?.errors;
  const first = fieldErrors ? Object.values(fieldErrors).flat()[0] : null;
  return first || error.response?.data?.message || error.message || fallback;
}
async function loadAdminLookups() {
  try {
    adminLookups.value = (await axios.get("/api/admin/lookups")).data;
    return adminLookups.value;
  } catch {
    notify("Could not load configured form options.");
    return [];
  }
}
function categoryTitle(category) {
  return category
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
function lookupKey(field) {
  return (
    {
      Title: "title",
      Gender: "gender",
      "Marital status": "marital_status",
      Religion: "religion",
      "Salary structure": "salary_structure",
      "Special categorization": "special_categorization",
      Level: "level",
      Step: "step",
      Department: "department",
      Designation: "designation",
      Qualification: "qualification",
      "PFA name": "pfa_name",
      "State of residence": "state",
      "State of origin": "state",
      "LGA of residence": "local_government",
      "LGA of origin": "local_government",
      "Senatorial district": "senatorial_district",
      "Bank name": "bank_name",
      "Next of kin relationship": "next_of_kin_relationship",
      "Select every category that applies": "job_categorization",
    }[field] || ""
  );
}
function dependentStateField(field) {
  return field === "LGA of residence"
    ? "State of residence"
    : field === "LGA of origin"
    ? "State of origin"
    : "";
}
function profileDropdownPlaceholder(field) {
  const parentField = dependentStateField(field);
  return parentField && !profileData.value[parentField]
    ? "Choose a state first"
    : "Select an option";
}
function getOptions(field) {
  const parentField = dependentStateField(field);
  if (parentField)
    return (
      store.lookups.local_government_by_state?.[
        profileData.value[parentField]
      ] || []
    );
  return store.lookups[lookupKey(field)] || [];
}
function setProfileDropdown(field, value) {
  profileData.value[field] = value;
  if (field === "State of residence")
    profileData.value["LGA of residence"] = "";
  if (field === "State of origin") profileData.value["LGA of origin"] = "";
}
async function startAddDropdown(field, educationIndex = null) {
  const parentField = dependentStateField(field);
  if (parentField && !profileData.value[parentField]) {
    notify(`Choose ${parentField.toLowerCase()} first.`, "warning");
    return;
  }
  const { isConfirmed, value } = await promptText({
    title: "Add a profile choice",
    label: `New ${field.toLowerCase()} option`,
    placeholder: "Enter the option name",
    confirmButtonText: "Add option",
  });
  if (!isConfirmed) return;
  addingDropdownField.value = field;
  addingEducationIndex.value = educationIndex;
  newDropdownOption.value = value.trim();
  await savePersonnelDropdownOption(field, educationIndex);
}
async function savePersonnelDropdownOption(field, educationIndex = null) {
  const enteredLabel = newDropdownOption.value.trim();
  if (!enteredLabel) return;
  dropdownOptionBusy.value = true;
  try {
    const parentField = dependentStateField(field);
    const parent = parentField ? profileData.value[parentField] : null;
    const { data } = await axios.post("/api/lookups", {
      category: lookupKey(field),
      label: enteredLabel,
      ...(parent ? { parent } : {}),
    });
    const label = data.option?.label || enteredLabel;
    await store.loadLookups();
    if (field === "Select every category that applies") {
      profileData.value.job_categories = [
        ...new Set([...(profileData.value.job_categories || []), label]),
      ];
    } else if (educationIndex !== null)
      educationDrafts.value[educationIndex].qualification = label;
    else profileData.value[field] = label;
    addingDropdownField.value = "";
    addingEducationIndex.value = null;
    newDropdownOption.value = "";
    notify(`“${label}” was added and selected.`);
  } catch (e) {
    notify(
      humanError(
        e,
        "We could not add that option. Please check the spelling and try again."
      )
    );
  } finally {
    dropdownOptionBusy.value = false;
  }
}
async function saveLookupOption() {
  try {
    const payload = {
      label: optionLabel.value,
      parent:
        optionCategory.value === "local_government"
          ? optionParent.value || null
          : null,
    };
    if (editingLookupId.value)
      await axios.put(`/api/admin/lookups/${editingLookupId.value}`, payload);
    else
      await axios.post("/api/admin/lookups", {
        category: optionCategory.value,
        ...payload,
      });
    await loadAdminLookups();
    await store.loadLookups();
    optionLabel.value = "";
    optionParent.value = "";
    editingLookupId.value = null;
    showOptions.value = false;
    notify("Personnel form options updated.");
  } catch (e) {
    notify(humanError(e, "Could not save this option."));
  }
}
function editLookupOption(option) {
  editingLookupId.value = option.id;
  optionCategory.value = option.category;
  optionLabel.value = option.label;
  optionParent.value = option.parent || "";
  showOptions.value = true;
}
async function deleteLookupOption(option) {
  const result = await confirmAction({
    title: "Remove this option?",
    text: `“${option.label}” will no longer appear as a choice for personnel.`,
    confirmButtonText: "Remove option",
  });
  if (!result.isConfirmed) return;
  try {
    await axios.delete(`/api/admin/lookups/${option.id}`);
    await loadAdminLookups();
    await store.loadLookups();
    notify(`${option.label} has been removed from active choices.`);
  } catch {
    notify("Could not remove this option.", "error");
  }
}
async function uploadInvitationFile(event) {
  bulkFile.value = event.target.files?.[0] || null;
  if (!bulkFile.value) return;
  const body = new FormData();
  body.append("file", bulkFile.value);
  try {
    const { data } = await axios.post("/api/admin/import", body);
    bulkResult.value = `${data.imported} invitations added · ${
      data.skipped
    } skipped${
      data.errors?.length ? ` · ${data.errors.length} rows need attention` : ""
    }`;
    notify("Email list processed.");
  } catch (e) {
    bulkResult.value = humanError(e, "We could not process this file.");
  } finally {
    event.target.value = "";
  }
}
async function uploadLookupFile(event) {
  const file = event.target.files?.[0];
  if (!file) return;
  const body = new FormData();
  body.append("file", file);
  try {
    const { data } = await axios.post("/api/admin/lookups/import", body);
    lookupImportResult.value = `${data.imported} options added · ${
      data.skipped
    } already existed${
      data.errors?.length ? ` · ${data.errors.length} rows need attention` : ""
    }`;
    await loadAdminLookups();
    await store.loadLookups();
    notify("Form options imported.");
  } catch (e) {
    lookupImportResult.value = humanError(
      e,
      "We could not import these options."
    );
  } finally {
    event.target.value = "";
  }
}
async function addInvitation() {
  try {
    await axios.post("/api/admin/invitations", { email: inviteEmail.value });
    showInvite.value = false;
    inviteEmail.value = "";
    notify("This email is approved to create a personnel account.");
  } catch (e) {
    notify(humanError(e, "Could not add this invitation."));
  }
}
async function addInvitationFromPage() {
  invitationPageMessage.value = "";
  invitationPageBusy.value = true;
  try {
    await axios.post("/api/admin/invitations", { email: inviteEmail.value });
    inviteEmail.value = "";
    invitationPageMessage.value =
      "Email approved. The person can now verify this address and create an account.";
    notify("Personnel email approved.");
  } catch (e) {
    invitationPageMessage.value = humanError(
      e,
      "Could not approve this email. Check the address and try again."
    );
  } finally {
    invitationPageBusy.value = false;
  }
}
async function loadDashboard() {
  if (!can("view_dashboard")) return;
  try {
    const { data } = await axios.get("/api/admin/dashboard");
    dashboardTotal.value = data.total_personnel;
    pendingReview.value = data.in_review;
    incompleteCount.value = data.incomplete_profiles;
    completeCount.value = data.complete_profiles;
    dashboardDepartments.value = data.departments;
    people.value = (data.recent_personnel || []).map((p) => ({
      ...p,
      id: p.file_number || `UCH-${p.id}`,
      color: "#e2f0e6",
      status:
        p.status === "in_review"
          ? "In review"
          : p.status === "complete"
          ? "Complete"
          : "Incomplete",
    }));
    stats.value = [
      {
        label: "Personnel records",
        value: Number(data.total_personnel).toLocaleString(),
        change: "Approved and registered accounts",
        icon: "mdi-account-group-outline",
        tone: "mint",
      },
      {
        label: "Profiles complete",
        value: `${
          data.total_personnel
            ? Math.round((data.complete_profiles * 100) / data.total_personnel)
            : 0
        }%`,
        change: `${data.complete_profiles} completed profiles`,
        icon: "mdi-check-decagram-outline",
        tone: "blue",
      },
      {
        label: "Awaiting review",
        value: String(data.in_review),
        change: "Submitted for HR review",
        icon: "mdi-clock-outline",
        tone: "amber",
      },
      {
        label: "Departments",
        value: String(data.departments),
        change: "Configured for personnel records",
        icon: "mdi-domain",
        tone: "lilac",
      },
    ];
  } catch {
    notify("Could not load the personnel dashboard.");
  }
}
async function loadOwnProfile() {
  try {
    const { profile } = await store.fetchProfile();
    profileStatus.value = profile?.status || "incomplete";
    profileData.value = profile?.data || {};
    if (!profileData.value.Email)
      profileData.value.Email = store.user?.email || "";
    educationDrafts.value = profileData.value.education?.length
      ? profileData.value.education
      : [{}];
    olevelDrafts.value = (profileData.value.olevel_results || [])
      .filter(
        (exam) =>
          exam.exam_year ||
          exam.exam_type ||
          (exam.subjects || []).some(
            (subject) => subject.subject || subject.grade
          )
      )
      .map((exam) => ({
        exam_year: exam.exam_year || "",
        exam_type: exam.exam_type || "",
        sitting: exam.sitting || "1st",
        subjects: exam.subjects?.length ? exam.subjects : [{}],
      }));
  } catch {
    notify("Could not load your personnel profile.");
  }
}
onMounted(async () => {
  window.addEventListener("pis-reverb-connected", onChatSocketConnected);
  window.addEventListener("pis-reverb-connecting", onChatSocketConnecting);
  window.addEventListener("pis-reverb-disconnected", onChatSocketDisconnected);
  chatRealtimeStatus.value = window.Echo
    ? window.Echo.connector.pusher.connection.state === "connected"
      ? "connected"
      : "connecting"
    : "offline";
  chatRefreshTimer = window.setInterval(() => {
    if (nav.value === "Messages" && chatRealtimeStatus.value !== "connected")
      loadChat(true);
  }, 10000);
  await store.loadLookups();
  if (["admin", "superadmin"].includes(store.user?.role)) {
    if (can("view_dashboard")) {
      nav.value = "Overview";
      await loadDashboard();
    } else if (can("view_personnel")) {
      nav.value = "Personnel directory";
      await fetchDirectory();
    } else if (can("manage_lookups")) {
      nav.value = "Dropdown options";
      await loadAdminLookups();
    } else if (can("manage_invitations")) nav.value = "Personnel invitations";
    if (store.user.role === "superadmin") await loadAdministrators();
  } else if (store.user) await loadOwnProfile();
});
onBeforeUnmount(() => {
  if (chatRefreshTimer) window.clearInterval(chatRefreshTimer);
  if (activeChatChannel && window.Echo) window.Echo.leave(activeChatChannel);
  window.removeEventListener("pis-reverb-connected", onChatSocketConnected);
  window.removeEventListener("pis-reverb-connecting", onChatSocketConnecting);
  window.removeEventListener(
    "pis-reverb-disconnected",
    onChatSocketDisconnected
  );
});
async function beginSignup() {
  signupError.value = "";
  signupBusy.value = true;
  try {
    if (signupStep.value === 0) {
      const result = await store.checkEmail(signupEmail.value);
      if (!result.available) throw new Error(result.message);
      signupStep.value = 1;
    } else if (signupStep.value === 1) {
      if (signupPassword.value !== signupConfirm.value)
        throw new Error("Your passwords do not match.");
      await store.beginSignup({
        email: signupEmail.value,
        password: signupPassword.value,
        password_confirmation: signupConfirm.value,
      });
      signupStep.value = 2;
    } else {
      const result = await store.verifyOtp({
        email: signupEmail.value,
        code: signupCode.value,
      });
      authEmail.value = signupEmail.value;
      authPassword.value = "";
      authSuccess.value = result.message;
      authError.value = "";
      authView.value = "signin";
      signupStep.value = 0;
      signupCode.value = "";
      signupPassword.value = "";
      signupConfirm.value = "";
    }
  } catch (e) {
    signupError.value = humanError(
      e,
      "We could not complete that step. Please try again."
    );
  } finally {
    signupBusy.value = false;
  }
}
function addEducation() {
  educationDrafts.value.push({});
}
function removeEducation(index) {
  if (educationDrafts.value.length > 1) educationDrafts.value.splice(index, 1);
  else educationDrafts.value = [{}];
}
function addOlevelSitting() {
  if (canAddOlevelSitting.value)
    olevelDrafts.value.push({ sitting: "1st", subjects: [{}] });
}
function removeOlevelSitting(index) {
  if (olevelDrafts.value.length > 1) olevelDrafts.value.splice(index, 1);
  else olevelDrafts.value = [];
}
function addOlevelSubject(exam) {
  if (exam.subjects.length < 30) exam.subjects.push({});
}
function removeOlevelSubject(exam, index) {
  if (exam.subjects.length > 1) exam.subjects.splice(index, 1);
  else exam.subjects[0] = {};
}
function profilePayload() {
  return {
    ...profileData.value,
    education: educationDrafts.value.filter((row) =>
      Object.values(row).some(Boolean)
    ),
    olevel_results: olevelDrafts.value
      .filter(
        (exam) =>
          exam.exam_year ||
          exam.exam_type ||
          (exam.subjects || []).some(
            (subject) => subject.subject || subject.grade
          )
      )
      .map((exam) => ({
        exam_year: exam.exam_year || "",
        exam_type: exam.exam_type || "",
        sitting: exam.sitting || "1st",
        subjects: (exam.subjects || []).filter(
          (subject) => subject.subject || subject.grade
        ),
      })),
  };
}
function goToProfileSection(index) {
  profileTab.value = Math.min(Math.max(index, 0), sections.length - 1);
  nextTick(() => {
    document
      .querySelector(".profile-side .step-item.selected")
      ?.scrollIntoView({
        behavior: "smooth",
        block: "nearest",
        inline: "center",
      });
    document
      .querySelector(".profile-form")
      ?.scrollIntoView({ behavior: "smooth", block: "start" });
  });
}
function profileSectionHasData(section) {
  if (section.title === "Education")
    return (
      educationDrafts.value.some((row) =>
        Object.values(row || {}).some((value) => String(value || "").trim())
      ) || olevelDrafts.value.length > 0
    );
  if (section.title === "Job categorization")
    return (profileData.value.job_categories || []).length > 0;
  return section.fields
    .filter((field) => field !== "Email")
    .some((field) => String(profileData.value[field] || "").trim());
}
async function clearProfileSection(section) {
  if (
    !section ||
    clearingProfileSection.value ||
    !profileSectionHasData(section)
  )
    return;
  const sectionKey = {
    Appointment: "appointment",
    "Personal details": "personal-details",
    "Contact & address": "contact-address",
    "Next of kin": "next-of-kin",
    "Job categorization": "job-categorization",
    Education: "education",
    "Bank details": "bank-details",
  }[section.title];
  if (!sectionKey) return;
  const confirmation = await confirmAction({
    title: `Clear ${section.title}?`,
    text: "This removes the saved and current entries here. Required details may need to be added again before you can submit your profile.",
    confirmButtonText: "Clear and save",
  });
  if (!confirmation.isConfirmed) return;

  const previousState = {
    profileData: JSON.parse(JSON.stringify(profileData.value)),
    education: educationDrafts.value.map((row) => ({ ...row })),
    olevel: olevelDrafts.value.map((exam) => ({
      ...exam,
      subjects: (exam.subjects || []).map((subject) => ({ ...subject })),
    })),
    status: profileStatus.value,
  };

  if (section.title === "Education") {
    educationDrafts.value = [{}];
    olevelDrafts.value = [];
  } else if (section.title === "Job categorization") {
    profileData.value.job_categories = [];
  } else {
    for (const field of section.fields) {
      profileData.value[field] =
        field === "Email" ? store.user?.email || "" : "";
    }
  }

  clearingProfileSection.value = true;
  try {
    const result = await store.clearProfileSection(sectionKey);
    profileStatus.value = result.profile.status;
    profileData.value = {
      ...(result.profile.data || {}),
      Email: result.profile.data?.Email || store.user?.email || "",
    };
    notify(
      section.title === "Education" && isJuniorStaff.value
        ? "Education and O’Level entries were cleared. Junior Staff will need a complete O’Level result before submitting."
        : `${section.title} entries were cleared and saved.`
    );
  } catch (error) {
    profileData.value = previousState.profileData;
    educationDrafts.value = previousState.education;
    olevelDrafts.value = previousState.olevel;
    profileStatus.value = previousState.status;
    notify(humanError(error, `Could not clear ${section.title}.`), "error");
  } finally {
    clearingProfileSection.value = false;
  }
}
async function saveProfile(advance = false) {
  if (!store.user) {
    notify("Sign in before saving your personnel profile.");
    return;
  }
  try {
    const data = profilePayload();
    if (
      advance &&
      profileTab.value === 0 &&
      !String(data["Date of first appointment"] || "").trim()
    ) {
      notify(
        "Please enter your date of first appointment before continuing.",
        "warning"
      );
      return;
    }
    const juniorOlevelMissing =
      advance &&
      sections[profileTab.value]?.title === "Education" &&
      isJuniorStaff.value &&
      !hasCompleteOlevelResult.value;
    const juniorOlevelMessage =
      "Junior Staff must add an O’Level result with the exam year, exam body, sitting, and at least one subject and grade before continuing.";
    const result = await store.saveProfile({ data });
    profileStatus.value = result.profile.status;
    if (juniorOlevelMissing) {
      notify(`${juniorOlevelMessage} Your entries have been saved.`, "warning");
      return;
    }
    if (advance && profileTab.value < sections.length - 1) {
      goToProfileSection(profileTab.value + 1);
      notify("Your profile has been saved.");
    } else if (advance && result.profile.status === "in_review") {
      notify("Your profile has been submitted for review.");
    } else if (advance) {
      notify(
        isJuniorStaff.value && !hasCompleteOlevelResult.value
          ? "Your profile is saved as a draft. Junior Staff must add a complete O’Level result before submitting."
          : "Your profile is saved as a draft. Complete the required fields, including your date of first appointment, before submitting.",
        "warning"
      );
    } else {
      notify("Your profile has been saved.");
    }
  } catch (e) {
    notify(humanError(e, "We could not save your profile. Please try again."));
  }
}
async function printPersonnelProfile() {
  if (!store.user) return;
  try {
    const data = profilePayload();
    const result = await store.saveProfile({ data });
    profileStatus.value = result.profile.status;
    profileData.value = result.profile.data || data;
    await nextTick();
    window.print();
  } catch (e) {
    notify(
      humanError(e, "Save your profile before printing, then try again."),
      "error"
    );
  }
}
async function loadChat(quiet = false) {
  if (chatBusy.value || !store.user) return;
  chatBusy.value = true;
  try {
    const params = {};
    if (chatSelectedPerson.value?.id)
      params.personnel_id = chatSelectedPerson.value.id;
    if (
      chatSearch.value.trim() &&
      ["admin", "superadmin"].includes(store.user.role)
    )
      params.search = chatSearch.value.trim();
    const { data } = await axios.get("/api/chat", { params });
    chatPeople.value = data.conversations || [];
    if (!["admin", "superadmin"].includes(store.user.role) || data.personnel) {
      chatSelectedPerson.value = data.personnel || null;
      chatMessages.value = data.messages || [];
    } else if (chatSelectedPerson.value) {
      chatMessages.value = data.messages || [];
    }
    chatError.value = "";
  } catch (e) {
    chatError.value = humanError(
      e,
      "Unable to load messages. Please try again."
    );
    if (!quiet) notify(chatError.value, "error");
  } finally {
    chatBusy.value = false;
  }
}
async function selectChatPerson(person) {
  chatSelectedPerson.value = {
    id: person.id,
    name: person.name,
    email: person.email,
  };
  chatMessages.value = [];
  await loadChat();
}
async function sendChatMessage() {
  const body = chatDraft.value.trim();
  if (!body || chatSending.value) return;
  if (
    ["admin", "superadmin"].includes(store.user?.role) &&
    !chatSelectedPerson.value
  )
    return;
  chatSending.value = true;
  chatError.value = "";
  try {
    const payload = { body };
    if (["admin", "superadmin"].includes(store.user?.role))
      payload.personnel_id = chatSelectedPerson.value.id;
    const { data } = await axios.post("/api/chat/messages", payload);
    chatDraft.value = "";
    if (!chatMessages.value.some((message) => message.id === data.message.id))
      chatMessages.value = [...chatMessages.value, data.message];
    if (["admin", "superadmin"].includes(store.user?.role))
      await loadChat(true);
  } catch (e) {
    chatError.value = humanError(
      e,
      "Your message could not be sent. Please try again."
    );
  } finally {
    chatSending.value = false;
  }
}
function onChatSocketConnected() {
  chatRealtimeStatus.value = "connected";
}
function onChatSocketConnecting() {
  chatRealtimeStatus.value = "connecting";
}
function onChatSocketDisconnected() {
  chatRealtimeStatus.value = "offline";
}
function receiveChatMessage(message) {
  if (!chatMessages.value.some((item) => item.id === message.id))
    chatMessages.value = [...chatMessages.value, message];
  if (
    ["admin", "superadmin"].includes(store.user?.role) &&
    chatSelectedPerson.value
  ) {
    chatPeople.value = chatPeople.value.map((person) =>
      person.id === chatSelectedPerson.value.id
        ? { ...person, last_message: message.body }
        : person
    );
  }
}
function subscribeChatChannel() {
  if (activeChatChannel && window.Echo) {
    window.Echo.leave(activeChatChannel);
    activeChatChannel = null;
  }
  if (nav.value !== "Messages" || !store.user) return;
  const isAdmin = ["admin", "superadmin"].includes(store.user.role);
  const personnelId = isAdmin ? chatSelectedPerson.value?.id : store.user.id;
  if (!personnelId) return;
  if (!window.Echo) {
    chatRealtimeStatus.value = "offline";
    return;
  }
  chatRealtimeStatus.value =
    window.Echo.connector.pusher.connection.state === "connected"
      ? "connected"
      : "connecting";
  activeChatChannel = `personnel.${personnelId}`;
  window.Echo.private(activeChatChannel).listen(
    ".personnel.message.sent",
    receiveChatMessage
  );
}
watch(
  () => [nav.value, store.user?.id, chatSelectedPerson.value?.id],
  subscribeChatChannel,
  { flush: "post" }
);
function navigate(item) {
  const permission = {
    Overview: "view_dashboard",
    "Personnel directory": "view_personnel",
    "Dropdown options": "manage_lookups",
    "Personnel invitations": "manage_invitations",
  }[item];
  if (permission && !can(permission)) {
    notify(
      "Your account does not have access to that section. Please contact your superadmin."
    );
    return;
  }
  nav.value = item;
  if (item === "Profile setup") profileTab.value = 0;
  if (item === "Personnel directory") fetchDirectory();
  if (item === "Dropdown options") loadAdminLookups();
  if (item === "Access & roles") loadAdministrators();
  if (item === "Messages") {
    chatSelectedPerson.value = null;
    chatMessages.value = [];
    loadChat();
  }
}
function clearDirectoryFilters() {
  directoryFilters.value = { name: "", email: "", ippis_no: "", file_no: "" };
  fetchDirectory(1);
}
async function fetchDirectory(page = 1) {
  if (!["admin", "superadmin"].includes(store.user?.role)) {
    directoryRows.value = [];
    notify("Sign in with an administrator account to view personnel records.");
    return;
  }
  directoryBusy.value = true;
  directoryPage.value = page;
  try {
    const r = await axios.get("/api/admin/personnel", {
      params: { ...directoryFilters.value, page },
    });
    directoryRows.value = r.data.data;
    directoryTotal.value = r.data.total;
  } catch (e) {
    directoryRows.value = [];
    notify(
      e.response?.status === 403
        ? "Administrator access is required to view personnel records."
        : "Unable to load personnel records."
    );
  } finally {
    directoryBusy.value = false;
  }
}
async function updatePersonnelStatus(row) {
  try {
    await axios.patch(`/api/admin/personnel/${row.id}/status`, {
      status: row.status,
    });
    notify("Personnel record status updated.");
  } catch (e) {
    notify(humanError(e, "Could not update this personnel record."));
    await fetchDirectory(directoryPage.value);
  }
}
async function loadAdministrators() {
  if (store.user?.role !== "superadmin") return;
  try {
    adminAccounts.value = (await axios.get("/api/admin/users")).data.map(
      (user) => ({ ...user, permissions: [...(user.permissions || [])] })
    );
  } catch (e) {
    adminAccountsMessage.value = humanError(
      e,
      "We could not load administrator access settings."
    );
  }
}
async function createAdministrator() {
  adminCreateMessage.value = "";
  adminCreateBusy.value = true;
  try {
    await axios.post("/api/admin/users", newAdmin.value);
    adminCreateMessage.value =
      "Administrator account created with the selected access. Share the sign-in details securely.";
    newAdmin.value = {
      name: "",
      email: "",
      password: "",
      password_confirmation: "",
      permissions: adminPermissionOptions.map((item) => item.key),
    };
    await loadAdministrators();
  } catch (e) {
    adminCreateMessage.value = humanError(
      e,
      "Could not create the administrator account."
    );
  } finally {
    adminCreateBusy.value = false;
  }
}
async function saveAdminPermissions(account) {
  const result = await confirmAction({
    title: "Save access changes?",
    text: `Update the permissions assigned to ${account.name}?`,
    confirmButtonText: "Save access",
    icon: "question",
  });
  if (!result.isConfirmed) return;
  adminPermissionsBusy.value = true;
  try {
    await axios.patch(`/api/admin/users/${account.id}/permissions`, {
      permissions: account.permissions,
    });
    adminAccountsMessage.value = `Access saved for ${account.name}.`;
    await loadAdministrators();
  } catch (e) {
    adminAccountsMessage.value = humanError(
      e,
      "Could not update administrator access."
    );
  } finally {
    adminPermissionsBusy.value = false;
  }
}
async function exportDirectory() {
  if (!["admin", "superadmin"].includes(store.user?.role)) {
    notify(
      "Sign in with an administrator account to export personnel records."
    );
    return;
  }
  try {
    const r = await axios.get("/api/admin/personnel/export", {
      params: directoryFilters.value,
      responseType: "blob",
    });
    const url = URL.createObjectURL(r.data);
    const a = document.createElement("a");
    a.href = url;
    a.download = "personnel_records.xlsx";
    a.click();
    URL.revokeObjectURL(url);
  } catch {
    notify(
      "Unable to export personnel records. Please check administrator access."
    );
  }
}
async function adminSignIn() {
  loginError.value = "";
  loginBusy.value = true;
  try {
    const r = await store.login({
      email: adminEmail.value,
      password: adminPassword.value,
    });
    if (!["admin", "superadmin"].includes(r.user.role)) {
      await store.logout();
      loginError.value =
        "This sign-in is for administrator accounts. Use personnel sign-up instead.";
      return;
    }
    nav.value = adminLanding();
    if (nav.value === "Personnel directory") await fetchDirectory(1);
    if (nav.value === "Overview") await loadDashboard();
  } catch (e) {
    loginError.value = humanError(
      e,
      "Unable to sign in. Check your email and password."
    );
  } finally {
    loginBusy.value = false;
  }
}
function openForgotPassword() {
  resetStep.value = "email";
  resetEmail.value = authEmail.value;
  resetCode.value = "";
  resetPassword.value = "";
  resetPasswordConfirm.value = "";
  resetError.value = "";
  resetNotice.value = "";
  authView.value = "forgot-password";
}
function returnToSignIn() {
  authView.value = "signin";
  resetError.value = "";
  authError.value = "";
}
async function requestPasswordResetCode() {
  resetError.value = "";
  if (!resetEmail.value.trim()) {
    resetError.value = "Enter your email address.";
    return;
  }
  authBusy.value = true;
  try {
    await store.csrf();
    await axios.post("/api/password/forgot", {
      email: resetEmail.value.trim().toLowerCase(),
    });
    resetEmail.value = resetEmail.value.trim().toLowerCase();
    resetStep.value = "code";
    resetNotice.value =
      "If an account exists for this email, a six-digit code has been sent. The code expires in 10 minutes.";
  } catch (e) {
    resetError.value = humanError(
      e,
      "We could not send a reset code. Please try again."
    );
  } finally {
    authBusy.value = false;
  }
}
function continuePasswordReset() {
  resetError.value = "";
  if (!/^\d{6}$/.test(resetCode.value)) {
    resetError.value = "Enter the six-digit code from your email.";
    return;
  }
  resetStep.value = "password";
}
async function submitPasswordReset() {
  resetError.value = "";
  if (!resetPasswordValid.value) {
    resetError.value =
      "Use 8–200 characters with uppercase and lowercase letters, a number, and a symbol.";
    return;
  }
  if (!resetPasswordsMatch.value) {
    resetError.value = "The passwords do not match.";
    return;
  }
  authBusy.value = true;
  try {
    await store.csrf();
    const response = await axios.post("/api/password/reset", {
      email: resetEmail.value,
      code: resetCode.value,
      password: resetPassword.value,
      password_confirmation: resetPasswordConfirm.value,
    });
    authEmail.value = resetEmail.value;
    authPassword.value = "";
    authSuccess.value =
      response.data.message ||
      "Your password has been changed. Sign in using your new password.";
    resetNotice.value =
      "Your password has been reset successfully. You can now sign in with your new password.";
    resetStep.value = "complete";
    resetPassword.value = "";
    resetPasswordConfirm.value = "";
  } catch (e) {
    resetError.value = humanError(
      e,
      "We could not reset your password. Please request a new code and try again."
    );
  } finally {
    authBusy.value = false;
  }
}

async function signIn() {
  authError.value = "";
  authSuccess.value = "";
  authBusy.value = true;
  try {
    const r = await store.login({
      email: authEmail.value,
      password: authPassword.value,
    });
    nav.value = ["admin", "superadmin"].includes(r.user.role)
      ? adminLanding()
      : "Profile setup";
    if (nav.value === "Overview") await loadDashboard();
    else if (nav.value === "Personnel directory") await fetchDirectory();
    else if (nav.value === "Dropdown options") await loadAdminLookups();
    else if (nav.value === "Access & roles") await loadAdministrators();
    else if (nav.value === "Profile setup") await loadOwnProfile();
  } catch (e) {
    authError.value = humanError(
      e,
      "We could not sign you in. Check the email and password, then try again."
    );
  } finally {
    authBusy.value = false;
  }
}
async function signOut() {
  const result = await confirmAction({
    title: "Sign out of Personnel Information System?",
    text: "You can sign back in at any time.",
    confirmButtonText: "Sign out",
    icon: "question",
  });
  if (!result.isConfirmed) return;
  await store.logout();
  authView.value = "signin";
  authError.value = "";
  authPassword.value = "";
}
</script>
<template>

  <div v-if="!store.user" class="auth-shell">
    <div class="auth-frame">
      <aside class="auth-story">
        <div class="auth-story-brand">
          <img style="width: 100px; height: auto;" :src="logoAsset" alt="University College Hospital, Ibadan" />
          <div><h2>University College Hospital</h2><h4>IBADAN</h4></div>
        </div>
        <div class="story-orbit orbit-one"></div>
        <div class="story-orbit orbit-two"></div>
        <div class="story-content">
          <h1>PERSONNEL INFORMATION SYSTEM</h1>
          <p>Your work matters to us as well as your records.</p>
          <div class="story-points">
            <div>
              <span><v-icon icon="mdi-heart-pulse" size="18" /></span>
              <div>
                <b>Made for the people behind the care</b
                ><small
                  >Your role, experience and service in one trusted
                  record.</small
                >
              </div>
            </div>
            <div>
              <span><v-icon icon="mdi-shield-check-outline" size="18" /></span>
              <div>
                <b>Private by design</b
                ><small
                  >Your information stays protected and in the right
                  hands.</small
                >
              </div>
            </div>
          </div>
        </div>
        <div class="story-foot">
          <span>UCH · IBADAN</span><span>PEOPLE FIRST, ALWAYS</span>
        </div>
      </aside>
      <main class="auth-main">
        <div class="auth-mobile-brand">
          <img :src="logoAsset" alt="UCH" /><span
            >Personnel Information System</span
          >
        </div>
        <div class="auth-card">
          <div v-if="authView !== 'forgot-password'" class="auth-tabs">
            <button
              :class="{ selected: authView === 'signin' }"
              @click="
                authView = 'signin';
                authError = '';
              "
            >
              Sign in</button
            ><button
              :class="{ selected: authView === 'signup' }"
              @click="
                authView = 'signup';
                signupStep = 0;
                signupError = '';
              "
            >
              Create account
            </button>
          </div>
          <section
            v-if="authView === 'forgot-password'"
            class="auth-reset-page"
            aria-labelledby="reset-page-title"
          >
            <div class="auth-heading">
              <span class="auth-overline">ACCOUNT RECOVERY</span>
              <h2 id="reset-page-title">
                {{
                  resetStep === "complete"
                    ? "Password updated"
                    : "Reset your password"
                }}
              </h2>
              <p v-if="resetStep === 'email'">
                Enter the email address linked to your UCH personnel account.
              </p>
              <p v-else-if="resetStep === 'code'">
                Enter the six-digit code sent to {{ resetEmail }}.
              </p>
              <p v-else-if="resetStep === 'password'">
                Choose a strong password for your account.
              </p>
              <p v-else>Your password has been changed successfully.</p>
            </div>
            <div v-if="resetNotice" class="auth-success" role="status">
              <v-icon icon="mdi-check-circle-outline" size="18" />{{
                resetNotice
              }}
            </div>
            <form
              v-if="resetStep === 'email'"
              class="auth-form"
              @submit.prevent="requestPasswordResetCode"
            >
              <label for="reset-email">Email address</label>
              <div class="auth-input-wrap">
                <v-icon icon="mdi-email-outline" size="18" /><input
                  id="reset-email"
                  v-model="resetEmail"
                  type="email"
                  autocomplete="email"
                  required
                  placeholder="you@example.com"
                />
              </div>
              <div v-if="resetError" class="auth-error" role="alert">
                <v-icon icon="mdi-alert-circle-outline" size="16" />{{
                  resetError
                }}
              </div>
              <button class="auth-submit" type="submit" :disabled="authBusy">
                <span>{{ authBusy ? "Sending code…" : "Send reset code" }}</span
                ><v-icon icon="mdi-arrow-right" size="18" />
              </button>

             <router-link
             @click="returnToSignIn"
              class="back-to-login"
              aria-label="Return to login page"
              style="cursor: pointer;"
            >
              <span>← Back to login</span>
            </router-link>

            </form>
            <form
              v-else-if="resetStep === 'code'"
              class="auth-form"
              @submit.prevent="continuePasswordReset"
            >
              <label for="reset-code">Six-digit verification code</label>
              <div class="auth-input-wrap">
                <v-icon icon="mdi-numeric" size="18" /><input
                  id="reset-code"
                  v-model="resetCode"
                  inputmode="numeric"
                  autocomplete="one-time-code"
                  maxlength="6"
                  pattern="[0-9]{6}"
                  required
                  placeholder="000000"
                />
              </div>
              <div class="auth-note">
                <v-icon icon="mdi-clock-outline" size="16" /><span
                  >Check your inbox. Your code expires in 10 minutes.</span
                >
              </div>
              <div v-if="resetError" class="auth-error" role="alert">
                <v-icon icon="mdi-alert-circle-outline" size="16" />{{
                  resetError
                }}
              </div>
              <button class="auth-submit" type="submit">
                <span>Continue</span><v-icon icon="mdi-arrow-right" size="18" />
              </button>
              <button
                class="auth-back"
                type="button"
                @click="
                  resetStep = 'email';
                  resetError = '';
                "
              >
                Change email address
              </button>
            </form>
            <form
              v-else-if="resetStep === 'password'"
              class="auth-form"
              @submit.prevent="submitPasswordReset"
            >
              <label for="reset-new-password">New password</label>
              <div class="auth-input-wrap password-input-wrap">
                <v-icon icon="mdi-lock-outline" size="18" /><input
                  id="reset-new-password"
                  v-model="resetPassword"
                  :type="showResetPassword ? 'text' : 'password'"
                  autocomplete="new-password"
                  required
                  placeholder="Enter a new password"
                /><button
                  class="password-toggle"
                  type="button"
                  :aria-label="
                    showResetPassword ? 'Hide password' : 'Show password'
                  "
                  :aria-pressed="showResetPassword"
                  @click="showResetPassword = !showResetPassword"
                >
                  <svg
                    v-if="!showResetPassword"
                    class="reset-eye-svg"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                  >
                    <path
                      d="M2.2 12s3.5-6.5 9.8-6.5 9.8 6.5 9.8 6.5-3.5 6.5-9.8 6.5S2.2 12 2.2 12Z"
                    />
                    <circle cx="12" cy="12" r="3" /></svg
                  ><svg
                    v-else
                    class="reset-eye-svg"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                  >
                    <path
                      d="M3 3l18 18M10.6 5.7A10.8 10.8 0 0 1 12 5.5c6.3 0 9.8 6.5 9.8 6.5a16.7 16.7 0 0 1-3.1 3.8M6.2 6.2C3.6 8 2.2 12 2.2 12s3.5 6.5 9.8 6.5c1.2 0 2.3-.2 3.3-.6"
                    />
                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                  </svg>
                </button>
              </div>
              <p class="auth-note">
                Use 8–200 characters with uppercase and lowercase letters, a
                number, and a symbol.
              </p>
              <label for="reset-confirm-password">Confirm new password</label>
              <div class="auth-input-wrap password-input-wrap">
                <v-icon icon="mdi-lock-check-outline" size="18" /><input
                  id="reset-confirm-password"
                  v-model="resetPasswordConfirm"
                  :type="showResetPasswordConfirm ? 'text' : 'password'"
                  autocomplete="new-password"
                  required
                  placeholder="Re-enter your new password"
                /><button
                  class="password-toggle"
                  type="button"
                  :aria-label="
                    showResetPasswordConfirm
                      ? 'Hide password confirmation'
                      : 'Show password confirmation'
                  "
                  :aria-pressed="showResetPasswordConfirm"
                  @click="showResetPasswordConfirm = !showResetPasswordConfirm"
                >
                  <svg
                    v-if="!showResetPasswordConfirm"
                    class="reset-eye-svg"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                  >
                    <path
                      d="M2.2 12s3.5-6.5 9.8-6.5 9.8 6.5 9.8 6.5-3.5 6.5-9.8 6.5S2.2 12 2.2 12Z"
                    />
                    <circle cx="12" cy="12" r="3" /></svg
                  ><svg
                    v-else
                    class="reset-eye-svg"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                  >
                    <path
                      d="M3 3l18 18M10.6 5.7A10.8 10.8 0 0 1 12 5.5c6.3 0 9.8 6.5 9.8 6.5a16.7 16.7 0 0 1-3.1 3.8M6.2 6.2C3.6 8 2.2 12 2.2 12s3.5 6.5 9.8 6.5c1.2 0 2.3-.2 3.3-.6"
                    />
                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                  </svg>
                </button>
              </div>
              <p
                v-if="resetPasswordConfirm"
                class="reset-match-status"
                :class="resetPasswordsMatch ? 'is-match' : 'is-mismatch'"
                aria-live="polite"
              >
                {{
                  resetPasswordsMatch
                    ? "Passwords match."
                    : "Passwords do not match."
                }}
              </p>
              <div v-if="resetError" class="auth-error" role="alert">
                <v-icon icon="mdi-alert-circle-outline" size="16" />{{
                  resetError
                }}
              </div>
              <button
                class="auth-submit"
                type="submit"
                :disabled="
                  authBusy || !resetPasswordValid || !resetPasswordsMatch
                "
              >
                <span>{{
                  authBusy ? "Updating password…" : "Reset password"
                }}</span
                ><v-icon icon="mdi-check" size="18" />
              </button>
            </form>
            <div v-else-if="resetStep === 'complete'" class="auth-form">
              <button
                class="auth-submit"
                type="button"
                @click="authView = 'signin'"
              >
                <span>Sign in with your new password</span>
                <v-icon icon="mdi-arrow-right" size="18" />
              </button>
            </div>
          </section>
          <template v-if="authView === 'signin'"
            ><div class="auth-heading">
              <span class="auth-overline">YOU ARE WELCOME HERE!</span>
              <!-- <h2>Come on in.</h2> -->
              <!-- <p>Sign up with email attached to your payslip.</p> -->
            </div>
            <div v-if="authSuccess" class="auth-success">
              <v-icon icon="mdi-check-circle-outline" size="18" />{{
                authSuccess
              }}
            </div>
            <form class="auth-form" @submit.prevent="signIn">
              <label for="auth-email">Email address</label>
              <div class="auth-input-wrap">
                <v-icon icon="mdi-email-outline" size="18" /><input
                  id="auth-email"
                  v-model="authEmail"
                  type="email"
                  autocomplete="username"
                  required
                  placeholder="you@example.com"
                />
              </div>
              <label for="auth-password">Password</label>
              <div class="auth-input-wrap password-input-wrap">
                <v-icon icon="mdi-lock-outline" size="18" /><input
                  id="auth-password"
                  v-model="authPassword"
                  :type="showAuthPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  required
                  placeholder="Enter your password"
                /><button
                  class="password-toggle"
                  type="button"
                  :aria-label="
                    showAuthPassword ? 'Hide password' : 'Show password'
                  "
                  :aria-pressed="showAuthPassword"
                  @click="showAuthPassword = !showAuthPassword"
                >
                  <svg
                    v-if="!showAuthPassword"
                    class="password-eye-icon"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                  >
                    <path
                      d="M2.2 12s3.6-7 9.8-7 9.8 7 9.8 7-3.6 7-9.8 7-9.8-7-9.8-7Z"
                    />
                    <circle cx="12" cy="12" r="3" /></svg
                  ><svg
                    v-else
                    class="password-eye-icon"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                  >
                    <path
                      d="m3 3 18 18M10.6 5.2A10.9 10.9 0 0 1 12 5c6.2 0 9.8 7 9.8 7a16 16 0 0 1-3.2 4.1M6.2 6.2C3.6 8.1 2.2 12 2.2 12s3.6 7 9.8 7c1.2 0 2.3-.3 3.3-.7"
                    />
                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                  </svg>
                </button>
              </div>
              <div class="auth-forgot-link">
                <button
                  type="button"
                  :disabled="authBusy"
                  @click="openForgotPassword"
                >
                  Forgot password?
                </button>
              </div>
              <div class="auth-error" v-if="authError">
                <v-icon icon="mdi-alert-circle-outline" size="16" />{{
                  authError
                }}
              </div>
              <button class="auth-submit" type="submit" :disabled="authBusy">
                <span>{{
                  authBusy ? "One moment…" : "Sign in to your account"
                }}</span
                ><v-icon icon="mdi-arrow-right" size="18" />
              </button>
            </form>
            <div class="auth-switch">
              <h2>First time here?</h2>
              ><button
                @click="
                  authView = 'signup';
                  signupStep = 0;
                  signupError = '';
                "
              >
                <span class="text-lg-body-1"> Create your account</span>
                <v-icon icon="mdi-arrow-up-right" size="15" />
              </button></div></template
          ><template v-else-if="authView === 'signup'"
            ><div class="auth-heading">
              <!-- <span class="auth-overline">YOUR UCH PERSONNEL RECORD</span> -->
              <h2>
                {{
                  signupStep === 0
                    ? "Let’s get you started."
                    : signupStep === 1
                    ? "Choose a password."
                    : "One last little check."
                }}
              </h2>
              <p>
                {{
                  signupStep === 0
                    ? "Start with the personal email attached to your payslip."
                    : signupStep === 1
                    ? "Use at least 8 characters with upper and lower case letters, a number and a symbol."
                    : "We sent a six-digit code to your email to make sure it’s really you."
                }}
              </p>
            </div>
            <div class="signup-progress">
              <span
                v-for="n in 3"
                :key="n"
                :class="{ done: n <= signupStep + 1 }"
              ></span
              ><small>STEP {{ signupStep + 1 }} OF 3</small>
            </div>
            <form
              v-if="signupStep === 0"
              class="auth-form"
              @submit.prevent="beginSignup"
            >
              <label for="signup-email">Personal email address</label>
              <div class="auth-input-wrap">
                <v-icon icon="mdi-email-outline" size="18" /><input
                  id="signup-email"
                  v-model="signupEmail"
                  type="email"
                  autocomplete="email"
                  required
                  placeholder="The email your admin has on file"
                />
              </div>
              <div class="auth-note">
                <v-icon icon="mdi-information-outline" size="16" /><span
                  >Your email is checked against email attached to payslip
                  before you can continue.</span
                >
              </div>
              <div class="auth-error" v-if="signupError">
                <v-icon icon="mdi-alert-circle-outline" size="16" />{{
                  signupError
                }}
              </div>
              <button class="auth-submit" type="submit" :disabled="signupBusy">
                <span>{{
                  signupBusy ? "Checking your email…" : "Check my email"
                }}</span
                ><v-icon icon="mdi-arrow-right" size="18" />
              </button>
            </form>
            <form
              v-else-if="signupStep === 1"
              class="auth-form"
              @submit.prevent="beginSignup"
            >
              <label for="signup-password">Create password</label>
              <div class="auth-input-wrap password-input-wrap">
                <v-icon icon="mdi-lock-outline" size="18" /><input
                  id="signup-password"
                  v-model="signupPassword"
                  :type="showSignupPassword ? 'text' : 'password'"
                  autocomplete="new-password"
                  minlength="12"
                  required
                  placeholder="12+ characters, letters, number and symbol"
                /><button
                  class="password-toggle"
                  type="button"
                  :aria-label="
                    showSignupPassword ? 'Hide password' : 'Show password'
                  "
                  :aria-pressed="showSignupPassword"
                  @click="showSignupPassword = !showSignupPassword"
                >
                  <svg
                    v-if="!showSignupPassword"
                    class="password-eye-icon"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                  >
                    <path
                      d="M2.2 12s3.6-7 9.8-7 9.8 7 9.8 7-3.6 7-9.8 7-9.8-7-9.8-7Z"
                    />
                    <circle cx="12" cy="12" r="3" /></svg
                  ><svg
                    v-else
                    class="password-eye-icon"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                  >
                    <path
                      d="m3 3 18 18M10.6 5.2A10.9 10.9 0 0 1 12 5c6.2 0 9.8 7 9.8 7a16 16 0 0 1-3.2 4.1M6.2 6.2C3.6 8.1 2.2 12 2.2 12s3.6 7 9.8 7c1.2 0 2.3-.3 3.3-.7"
                    />
                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                  </svg>
                </button>
              </div>
              <label for="signup-confirm">Confirm password</label>
              <div class="auth-input-wrap password-input-wrap">
                <v-icon icon="mdi-lock-check-outline" size="18" /><input
                  id="signup-confirm"
                  v-model="signupConfirm"
                  :type="showSignupConfirm ? 'text' : 'password'"
                  autocomplete="new-password"
                  required
                  placeholder="Type it once more"
                /><button
                  class="password-toggle"
                  type="button"
                  :aria-label="
                    showSignupConfirm
                      ? 'Hide password confirmation'
                      : 'Show password confirmation'
                  "
                  :aria-pressed="showSignupConfirm"
                  @click="showSignupConfirm = !showSignupConfirm"
                >
                  <svg
                    v-if="!showSignupConfirm"
                    class="password-eye-icon"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                  >
                    <path
                      d="M2.2 12s3.6-7 9.8-7 9.8 7 9.8 7-3.6 7-9.8 7-9.8-7-9.8-7Z"
                    />
                    <circle cx="12" cy="12" r="3" /></svg
                  ><svg
                    v-else
                    class="password-eye-icon"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                  >
                    <path
                      d="m3 3 18 18M10.6 5.2A10.9 10.9 0 0 1 12 5c6.2 0 9.8 7 9.8 7a16 16 0 0 1-3.2 4.1M6.2 6.2C3.6 8.1 2.2 12 2.2 12s3.6 7 9.8 7c1.2 0 2.3-.3 3.3-.7"
                    />
                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                  </svg>
                </button>
              </div>
              <div class="auth-error" v-if="signupError">
                <v-icon icon="mdi-alert-circle-outline" size="16" />{{
                  signupError
                }}
              </div>
              <button class="auth-submit" type="submit" :disabled="signupBusy">
                <span>{{
                  signupBusy
                    ? "Sending your code…"
                    : "Send me a verification code"
                }}</span
                ><v-icon icon="mdi-arrow-right" size="18" /></button
              ><button
                class="auth-back"
                type="button"
                @click="
                  signupStep = 0;
                  signupError = '';
                "
              >
                Back to email
              </button>
            </form>
            <form v-else class="auth-form" @submit.prevent="beginSignup">
              <label for="signup-code">Your six-digit code</label>
              <div class="auth-input-wrap code-input-wrap">
                <v-icon icon="mdi-numeric" size="18" /><input
                  id="signup-code"
                  v-model="signupCode"
                  inputmode="numeric"
                  autocomplete="one-time-code"
                  maxlength="6"
                  pattern="[0-9]{6}"
                  required
                  placeholder="000000"
                />
              </div>
              <div class="auth-note">
                <v-icon icon="mdi-clock-outline" size="16" /><span
                  >Check your inbox. Your code is good for 10 minutes.</span
                >
              </div>
              <div class="auth-error" v-if="signupError">
                <v-icon icon="mdi-alert-circle-outline" size="16" />{{
                  signupError
                }}
              </div>
              <button class="auth-submit" type="submit" :disabled="signupBusy">
                <span>{{
                  signupBusy
                    ? "Verifying your email…"
                    : "Verify email & continue"
                }}</span
                ><v-icon icon="mdi-check" size="18" /></button
              ><button
                class="auth-back"
                type="button"
                @click="
                  signupStep = 1;
                  signupError = '';
                "
              >
                Back to password
              </button>
            </form>
            <div class="auth-switch">
              <span>Already have an account?</span
              ><button
                @click="
                  authView = 'signin';
                  authError = '';
                "
              >
                Sign in instead <v-icon icon="mdi-arrow-up-right" size="15" />
              </button></div
          ></template>
          <div class="auth-secure">
            <v-icon icon="mdi-shield-lock-outline" size="15" /><span
              >Secure sign-in · Your personal details are kept private</span
            >
          </div>
        </div>
        <footer class="auth-footer">
          <span
            >Having trouble getting in? Contact your PIS administrators @ Smart
            Classroom 2.</span
          ><span class="auth-powered-credit"
            >Powered by Information Technology Department, UCH, IB.</span
          >
        </footer>
      </main>
    </div>
  </div>
  <div v-else class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <img class="brand-logo" :src="logoAsset" alt="UCH logo" />
        <div>
          <strong>Personnel Information System</strong><small>UCH, IB</small>
        </div>
      </div>
      <div class="nav-label">WORKSPACE</div>
      <button
        v-for="item in adminPrimaryItems"
        :key="item"
        class="nav-item"
        :class="{ active: nav === item }"
        @click="navigate(item)"
      >
        <v-icon
          :icon="
            item === 'Overview'
              ? 'mdi-view-dashboard-outline'
              : item === 'Personnel directory'
              ? 'mdi-account-multiple-outline'
              : item === 'Messages'
              ? 'mdi-message-text-outline'
              : 'mdi-clipboard-edit-outline'
          "
          size="19"
        /><span>{{ item }}</span
        ><span v-if="item === 'Profile setup'" class="nav-dot"></span>
      </button>
      <div v-if="adminManageItems.length" class="nav-label manage-label">
        MANAGE
      </div>
      <template v-if="adminManageItems.length"
        ><button
          v-for="item in adminManageItems"
          :key="item"
          class="nav-item"
          :class="{ active: nav === item }"
          @click="navigate(item)"
        >
          <v-icon
            :icon="
              item === 'Dropdown options'
                ? 'mdi-format-list-bulleted'
                : item === 'Personnel invitations'
                ? 'mdi-email-check-outline'
                : 'mdi-shield-account-outline'
            "
            size="19"
          /><span>{{ item }}</span>
        </button></template
      >
      <div class="sidebar-bottom">
        <div class="help-card">
          <span class="help-icon"
            ><v-icon icon="mdi-chat-question-outline" size="18" /></span
          ><b>Need a hand?</b>
          <p>Our People team is happy to help.</p>
          <button @click="notify('ITD Team')">
            Get support <v-icon icon="mdi-arrow-top-right" size="14" />
          </button>
        </div>
        <div class="user-row">
          <div class="user-avatar">
            {{
              (store.user?.name || "UC")
                .split(" ")
                .map((part) => part[0])
                .join("")
                .slice(0, 2)
                .toUpperCase()
            }}
          </div>
          <div class="user-copy">
            <b>{{ store.user?.name || "UCH colleague" }}</b
            ><small>{{
              store.user?.role === "superadmin"
                ? "Super administrator"
                : store.user?.role === "admin"
                ? "Administrator"
                : "Personnel"
            }}</small>
          </div>
          <v-icon icon="mdi-dots-horizontal" />
        </div>
      </div>
    </aside>
    <main class="main">
      <header class="topbar">
        <div class="crumb">
          <span>Workspace</span
          ><v-icon icon="mdi-chevron-right" size="15" /><b>{{ nav }}</b>
        </div>
        <div class="top-actions">
          <button class="admin-signin-link" @click="signOut">
            <svg
              class="signout-icon"
              viewBox="0 0 24 24"
              fill="none"
              aria-hidden="true"
            >
              <path
                d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path
                d="m16 17 5-5-5-5M21 12H9"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <span style="color: crimson">Sign out</span>
          </button>
          <div class="secure-pill">
            <v-icon icon="mdi-shield-check-outline" size="16" /> Secure
            workspace
          </div>
          <button class="icon-button" @click="notify('You’re all caught up.')">
            <v-icon icon="mdi-bell-outline" /><i></i>
          </button>
          <div class="tiny-avatar">
            {{
              (store.user?.name || "UC")
                .split(" ")
                .map((part) => part[0])
                .join("")
                .slice(0, 2)
                .toUpperCase()
            }}
          </div>
        </div>
      </header>
      <section v-if="nav === 'Overview'" class="content">
        <div class="welcome-row">
          <div>
            <div class="eyebrow">
              <span class="live-dot"></span> {{ todayLabel }}
            </div>
            <h1>Good morning, {{ displayName }} <span>☀️</span></h1>
            <p class="subhead">
              Here’s what’s happening across your people records today.
            </p>
          </div>
          <button
            v-if="can('manage_invitations')"
            class="primary-btn"
            @click="showInvite = true"
          >
            <v-icon icon="mdi-plus" size="18" /> Add personnel
          </button>
        </div>
        <div class="stats-grid dashboard-metrics">
          <article class="stat-card" v-for="s in stats" :key="s.label">
            <div class="stat-top">
              <span>{{ s.label }}</span
              ><span class="stat-icon" :class="s.tone"
                ><v-icon :icon="s.icon" size="19"
              /></span>
            </div>
            <div class="stat-value">{{ s.value }}</div>
            <div class="stat-note">{{ s.change }}</div>
          </article>
        </div>
        <section class="dashboard-analytics" aria-label="Personnel analytics">
          <article class="analytics-card readiness-card">
            <div class="analytics-card-heading">
              <div>
                <span class="form-kicker">PROFILE HEALTH</span>
                <h2>Profile completion</h2>
                <p>How far personnel records have progressed.</p>
              </div>
              <span class="analytics-period"
                ><v-icon icon="mdi-chart-donut" size="16" /> Overall</span
              >
            </div>
            <div class="readiness-content">
              <div class="readiness-ring" :style="profileRingStyle">
                <div>
                  <b>{{ profileCompletionPercent }}<small>%</small></b
                  ><span>complete</span>
                </div>
              </div>
              <div class="readiness-legend">
                <div>
                  <i class="legend-dot complete-dot"></i
                  ><span
                    ><b>Complete</b><small>Ready for HR records</small></span
                  ><strong>{{ completeCount.toLocaleString() }}</strong>
                </div>
                <div>
                  <i class="legend-dot review-dot"></i
                  ><span
                    ><b>In review</b
                    ><small>Submitted for verification</small></span
                  ><strong>{{ pendingReview.toLocaleString() }}</strong>
                </div>
                <div>
                  <i class="legend-dot incomplete-dot"></i
                  ><span
                    ><b>In progress</b
                    ><small>Personnel can continue editing</small></span
                  ><strong>{{ incompleteCount.toLocaleString() }}</strong>
                </div>
              </div>
            </div>
            <div class="analytics-footnote">
              <v-icon icon="mdi-information-outline" size="15" /> Based on
              {{ dashboardTotal.toLocaleString() }} registered personnel records
            </div>
          </article>
          <article class="analytics-card workforce-card">
            <div class="analytics-card-heading">
              <div>
                <span class="form-kicker">WORKFORCE SNAPSHOT</span>
                <h2>People at a glance</h2>
                <p>Current reach of your personnel directory.</p>
              </div>
              <span class="snapshot-icon"
                ><v-icon icon="mdi-account-group-outline" size="20"
              /></span>
            </div>
            <div class="snapshot-total">
              <strong>{{ dashboardTotal.toLocaleString() }}</strong
              ><span>registered personnel</span>
            </div>
            <div class="snapshot-track">
              <i :style="{ width: `${profileCompletionPercent}%` }"></i>
            </div>
            <div class="snapshot-caption">
              <span>Profile completion</span
              ><b>{{ profileCompletionPercent }}%</b>
            </div>
            <div class="snapshot-divider"></div>
            <div class="snapshot-detail">
              <span class="snapshot-detail-icon departments-icon"
                ><v-icon icon="mdi-domain" size="18" /></span
              ><span
                ><b>{{ dashboardDepartments.toLocaleString() }} departments</b
                ><small>Configured across the directory</small></span
              ><button
                type="button"
                aria-label="View personnel directory"
                @click="navigate('Personnel directory')"
              >
                <v-icon icon="mdi-arrow-up-right" size="18" />
              </button>
            </div>
            <div class="snapshot-detail">
              <span class="snapshot-detail-icon review-icon"
                ><v-icon icon="mdi-clipboard-check-outline" size="18" /></span
              ><span
                ><b>{{ pendingReview.toLocaleString() }} profiles to review</b
                ><small>Submitted and awaiting HR attention</small></span
              ><button
                type="button"
                aria-label="Review personnel records"
                @click="navigate('Personnel directory')"
              >
                <v-icon icon="mdi-arrow-up-right" size="18" />
              </button>
            </div>
          </article>
        </section>
        <div class="section-heading">
          <div>
            <h2>Recently updated</h2>
            <p>Your organisation’s personnel records, all in one place.</p>
          </div>
          <button class="text-action" @click="navigate('Personnel directory')">
            View directory <v-icon icon="mdi-arrow-right" size="16" />
          </button>
        </div>
        <div class="directory-card">
          <div class="directory-toolbar">
            <div class="table-title">
              <div class="mini-icon">
                <v-icon icon="mdi-account-multiple-outline" size="18" />
              </div>
              <div>
                <b>Recently updated</b
                ><small>Latest changes to your people records</small>
              </div>
            </div>
            <div class="table-tools">
              <div class="search-box">
                <v-icon icon="mdi-magnify" size="18" /><input
                  v-model="search"
                  placeholder="Search people"
                />
              </div>
              <button
                class="filter-btn"
                @click="navigate('Personnel directory')"
              >
                <v-icon icon="mdi-filter-variant" size="17" /> All records</button
              ><button
                class="more-btn"
                @click="notify('More directory options')"
              >
                <v-icon icon="mdi-dots-horizontal" />
              </button>
            </div>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>PERSON</th>
                  <th>DEPARTMENT</th>
                  <th>PERSONNEL ID</th>
                  <th>PROFILE STATUS</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="p in filtered"
                  :key="p.id"
                  @click="navigate('Personnel directory')"
                >
                  <td>
                    <div class="person-cell">
                      <div
                        class="person-avatar"
                        :style="{ background: p.color }"
                      >
                        {{ p.initials }}
                      </div>
                      <div>
                        <b>{{ p.name }}</b
                        ><small>{{ p.role }}</small>
                      </div>
                    </div>
                  </td>
                  <td>{{ p.dept }}</td>
                  <td>
                    <span class="id-pill">{{ p.id }}</span>
                  </td>
                  <td>
                    <span
                      class="status-pill"
                      :class="p.status.toLowerCase().replace(' ', '-')"
                      ><i></i>{{ p.status }}</span
                    >
                  </td>
                  <td>
                    <button
                      class="row-more"
                      @click.stop="notify(`${p.name} · personnel profile`)"
                    >
                      <v-icon icon="mdi-dots-horizontal" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="table-footer">
            <span
              >Showing <b>{{ filtered.length }}</b> recently updated of
              {{ dashboardTotal.toLocaleString() }} personnel</span
            ><button
              class="text-action"
              @click="navigate('Personnel directory')"
            >
              Open full directory <v-icon icon="mdi-arrow-right" size="15" />
            </button>
          </div>
        </div>
        <div class="bottom-grid">
          <div class="completion-card">
            <div class="completion-icon">
              <v-icon icon="mdi-clipboard-check-outline" />
            </div>
            <div class="completion-copy">
              <b>A little nudge goes a long way</b>
              <p>
                {{ incompleteCount }} personnel still have a few details to add
                to their profiles.
              </p>
              <button @click="navigate('Personnel directory')">
                Review incomplete profiles
                <v-icon icon="mdi-arrow-right" size="15" />
              </button>
            </div>
            <div class="progress-ring">
              <span>{{ incompleteCount }}</span>
            </div>
          </div>
          <div class="activity-card">
            <div class="activity-head">
              <b>Quick links</b><v-icon icon="mdi-dots-horizontal" />
            </div>
            <button @click="navigate('Personnel invitations')">
              <span class="quick-icon qi-green"
                ><v-icon icon="mdi-file-upload-outline" /></span
              ><span
                ><b>Import personnel</b
                ><small>Upload a CSV of approved emails</small></span
              ><v-icon icon="mdi-arrow-up-right" size="17" /></button
            ><button @click="navigate('Dropdown options')">
              <span class="quick-icon qi-blue"
                ><v-icon icon="mdi-tune-variant" /></span
              ><span
                ><b>Manage form options</b
                ><small>Keep lists current and relevant</small></span
              ><v-icon icon="mdi-arrow-up-right" size="17" />
            </button>
          </div>
        </div>
      </section>
      <section v-else-if="nav === 'Profile setup'" class="content form-content">
        <div class="welcome-row">
          <div>
            <div class="eyebrow">
              PERSONNEL INFORMATION <span class="eyebrow-slash">/</span> PROFILE
            </div>
            <h1>Your personnel profile</h1>
            <p class="subhead">
              A complete record helps us support you throughout your career.
            </p>
          </div>
          <div class="profile-heading-actions">
            <span
              class="draft-pill"
              :class="`status-${profileStatus.replace('_', '-')}`"
              ><i></i
              >{{
                profileStatus === "complete"
                  ? "Profile complete"
                  : profileStatus === "in_review"
                  ? "Submitted for review"
                  : "Draft · save as you go"
              }}</span
            ><button
              class="profile-print-button"
              type="button"
              @click="printPersonnelProfile"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M7 14h10v7H7z"
                />
                <path d="M17 11h.01" /></svg
              >Save &amp; print profile
            </button>
          </div>
        </div>
        <div class="profile-layout">
          <nav class="profile-side" aria-label="Personnel profile sections">
            <div class="profile-person">
              <div class="profile-photo">
                {{
                  (store.user?.name || "UC")
                    .split(" ")
                    .map((part) => part[0])
                    .join("")
                    .slice(0, 2)
                    .toUpperCase()
                }}
              </div>
              <b>{{ store.user?.name || store.user?.email }}</b
              ><small>UCH personnel account</small>
            </div>
            <!-- <div style="margin-left: 5px !important">Jump to a section</div> -->
            <button
              v-for="(s, i) in sections"
              :key="s.title"
              type="button"
              class="step-item"
              :class="{ selected: profileTab === i }"
              :aria-current="profileTab === i ? 'step' : undefined"
              @click="goToProfileSection(i)"
            >
              <span class="step-icon"><v-icon :icon="s.icon" size="17" /></span
              ><span>{{ s.title }}</span
              ><v-icon
                v-if="i < 3"
                icon="mdi-check-circle"
                class="step-check"
                size="16"
              />
            </button>
            <div class="side-hint">
              <v-icon icon="mdi-lock-outline" size="15" /> Only authorised HR
              staff can view your details.
            </div>
          </nav>
          <div class="profile-form">
            <div class="form-head">
              <div>
                <span class="form-kicker"
                  >SECTION {{ String(profileTab + 1).padStart(2, "0") }} OF
                  {{ String(sections.length).padStart(2, "0") }}</span
                >
                <h2>{{ sections[profileTab].title }}</h2>
                <p>
                  Select & fill the details that apply to you. You can save and
                  come back at any time.
                </p>
              </div>
              <div class="form-head-actions">
                <button
                  type="button"
                  class="clear-section-button"
                  :disabled="
                    clearingProfileSection ||
                    !profileSectionHasData(sections[profileTab])
                  "
                  @click="clearProfileSection(sections[profileTab])"
                >
                  <svg
                    class="clear-section-icon"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                  >
                    <path
                      d="M4 7h16M9 7V4h6v3m-9 0 1 13h10l1-13M10 11v5m4-5v5"
                    />
                  </svg>
                  {{ clearingProfileSection ? "Clearing…" : "Delete section" }}
                </button>
                <span class="required-note"><i>*</i> Required</span>
              </div>
            </div>
            <div
              v-if="sections[profileTab].title === 'Job categorization'"
              class="job-options"
            >
              <label
                v-for="x in getOptions('Select every category that applies')"
                :key="x"
                ><input
                  type="checkbox"
                  :value="x"
                  :checked="(profileData.job_categories || []).includes(x)"
                  @change="
                    $event.target.checked
                      ? (profileData.job_categories = [
                          ...(profileData.job_categories || []),
                          x,
                        ])
                      : (profileData.job_categories = (
                          profileData.job_categories || []
                        ).filter((y) => y !== x))
                  "
                />
                <span>{{ x }}</span></label
              ><button
                type="button"
                class="add-personnel-option"
                @click="startAddDropdown('Select every category that applies')"
              >
                Missing a category? Add it
              </button>
              <div
                v-if="
                  addingDropdownField === 'Select every category that applies'
                "
                class="inline-option-add"
              >
                <input
                  v-model="newDropdownOption"
                  maxlength="120"
                  placeholder="Type the category you need"
                  @keyup.enter="
                    savePersonnelDropdownOption(
                      'Select every category that applies'
                    )
                  "
                /><button
                  type="button"
                  :disabled="dropdownOptionBusy || !newDropdownOption.trim()"
                  @click="
                    savePersonnelDropdownOption(
                      'Select every category that applies'
                    )
                  "
                >
                  {{ dropdownOptionBusy ? "Adding…" : "Add & select" }}
                </button>
              </div>
            </div>
            <div
              v-else-if="sections[profileTab].title === 'Education'"
              class="education-list"
            >
              <article
                v-for="(education, index) in educationDrafts"
                :key="index"
                class="education-entry"
              >
                <div class="education-entry-head">
                  <div>
                    <b>Education {{ String(index + 1).padStart(2, "0") }}</b
                    ><small
                      >Add your institution and qualification details.</small
                    >
                  </div>
                  <button
                    v-if="
                      educationDrafts.length > 1 ||
                      Object.values(education || {}).some(Boolean)
                    "
                    class="remove-education"
                    @click="removeEducation(index)"
                  >
                    <svg
                      class="interface-icon"
                      viewBox="0 0 24 24"
                      aria-hidden="true"
                      focusable="false"
                    >
                      <path
                        d="M4 7h16M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3"
                      /></svg
                    ><span>Remove</span>
                  </button>
                </div>
                <div class="fields-grid">
                  <div>
                    <label>Institution</label
                    ><input
                      :value="education.institution"
                      @input="education.institution = $event.target.value"
                      placeholder="University or institution"
                    />
                  </div>
                  <div>
                    <label>Course</label
                    ><input
                      :value="education.course"
                      @input="education.course = $event.target.value"
                      placeholder="Course of study"
                    />
                  </div>
                  <div class="education-qualification">
                    <label>Qualification</label
                    ><select v-model="education.qualification">
                      <option value="" disabled>
                        Select your qualification
                      </option>
                      <option
                        v-for="choice in getOptions('Qualification')"
                        :key="choice"
                        :value="choice"
                      >
                        {{ choice }}
                      </option></select
                    ><button
                      type="button"
                      class="add-personnel-option"
                      @click="startAddDropdown('Qualification', index)"
                    >
                      Can’t find yours? Add it
                    </button>
                    <div
                      v-if="
                        addingDropdownField === 'Qualification' &&
                        addingEducationIndex === index
                      "
                      class="inline-option-add"
                    >
                      <input
                        v-model="newDropdownOption"
                        maxlength="120"
                        placeholder="Type your qualification"
                        @keyup.enter="
                          savePersonnelDropdownOption('Qualification', index)
                        "
                      /><button
                        type="button"
                        :disabled="
                          dropdownOptionBusy || !newDropdownOption.trim()
                        "
                        @click="
                          savePersonnelDropdownOption('Qualification', index)
                        "
                      >
                        {{ dropdownOptionBusy ? "Adding…" : "Add & select" }}
                      </button>
                    </div>
                  </div>
                  <div>
                    <label>Year of graduation</label
                    ><input
                      :value="education.year"
                      @input="education.year = $event.target.value"
                      type="number"
                      min="1950"
                      max="2100"
                      placeholder="YYYY"
                    />
                  </div>
                </div>
              </article>
              <button class="add-education" @click="addEducation">
                <v-icon icon="mdi-plus" size="17" /> Add another qualification
              </button>
              <p class="education-note">
                <v-icon icon="mdi-information-outline" size="15" /> You can add
                as many education records as apply to you.
              </p>
              <section class="olevel-panel">
                <div class="olevel-panel-heading">
                  <div>
                    <span class="form-kicker"
                      >SECONDARY SCHOOL QUALIFICATIONS</span
                    >
                    <h3>O’Level result(s)</h3>
                    <p v-if="isJuniorStaff">
                      Compulsory for Junior Staff. Enter at least one complete
                      result with an exam year, exam body, sitting, and a
                      subject with its grade.
                    </p>
                    <p v-else>
                      Optional for Senior Staff. You may save complete or
                      partial O’Level details without making them a submission
                      requirement.
                    </p>
                  </div>
                  <span class="olevel-limit-pill">{{
                    isJuniorStaff
                      ? nursingSchoolProfile
                        ? "Required · One sitting"
                        : "Required · Up to two sittings per exam body"
                      : "Optional · No completion requirement"
                  }}</span>
                </div>
                <article
                  v-for="(exam, examIndex) in olevelDrafts"
                  :key="examIndex"
                  class="olevel-exam-card"
                >
                  <div class="olevel-exam-heading">
                    <div>
                      <b
                        >Examination sitting
                        {{ String(examIndex + 1).padStart(2, "0") }}</b
                      ><small
                        >Add whichever details and subjects you have.</small
                      >
                    </div>
                    <button
                      v-if="olevelDrafts.length > 0"
                      type="button"
                      class="remove-education"
                      @click="removeOlevelSitting(examIndex)"
                    >
                      <span aria-hidden="true">×</span> Remove sitting
                    </button>
                  </div>
                  <div class="fields-grid olevel-fields">
                    <div>
                      <label>Exam year</label
                      ><input
                        v-model="exam.exam_year"
                        type="number"
                        min="1950"
                        :max="new Date().getFullYear()"
                        placeholder="e.g. 2023"
                      />
                    </div>
                    <div>
                      <label>Exam type / body</label
                      ><select v-model="exam.exam_type">
                        <option value="" disabled>Select exam body</option>
                        <option
                          v-for="type in [
                            'WASSCE (WAEC)',
                            'NECO SSCE',
                            'NABTEB',
                            'Other',
                          ]"
                          :key="type"
                          :value="type"
                          :disabled="
                            isJuniorStaff &&
                            olevelDrafts.filter(
                              (other) =>
                                other !== exam && other.exam_type === type
                            ).length >= 2
                          "
                        >
                          {{ type }}
                        </option>
                      </select>
                    </div>
                    <div>
                      <label>Sitting</label
                      ><select v-model="exam.sitting">
                        <option
                          value="1st"
                          :disabled="
                            isJuniorStaff &&
                            olevelDrafts.some(
                              (other) =>
                                other !== exam &&
                                other.exam_type === exam.exam_type &&
                                other.sitting === '1st'
                            )
                          "
                        >
                          1st sitting
                        </option>
                        <option
                          value="2nd"
                          :disabled="
                            isJuniorStaff &&
                            olevelDrafts.some(
                              (other) =>
                                other !== exam &&
                                other.exam_type === exam.exam_type &&
                                other.sitting === '2nd'
                            )
                          "
                        >
                          2nd sitting
                        </option>
                      </select>
                    </div>
                  </div>
                  <div class="olevel-subject-head">
                    <div>
                      <b>Subjects and grades</b
                      ><small>Add each subject shown on this result.</small>
                    </div>
                  </div>
                  <div
                    v-for="(subject, subjectIndex) in exam.subjects"
                    :key="subjectIndex"
                    class="olevel-subject-row"
                  >
                    <div>
                      <label>Subject</label
                      ><input
                        v-model="subject.subject"
                        maxlength="120"
                        placeholder="e.g. English Language"
                      />
                    </div>
                    <div>
                      <label>Grade</label
                      ><select v-model="subject.grade">
                        <option value="" disabled>Select grade</option>
                        <option
                          v-for="grade in [
                            'A1',
                            'B2',
                            'B3',
                            'C4',
                            'C5',
                            'C6',
                            'D7',
                            'E8',
                            'F9',
                          ]"
                          :key="grade"
                          :value="grade"
                        >
                          {{ grade }}
                        </option>
                      </select>
                    </div>
                    <button
                      type="button"
                      class="olevel-remove-subject"
                      :aria-label="`Remove subject ${subjectIndex + 1}`"
                      @click="removeOlevelSubject(exam, subjectIndex)"
                    >
                      −
                    </button>
                  </div>
                  <button
                    type="button"
                    class="olevel-add-subject"
                    :disabled="exam.subjects.length >= 30"
                    @click="addOlevelSubject(exam)"
                  >
                    ＋ Add subject and grade
                  </button>
                </article>
                <button
                  type="button"
                  class="olevel-add-exam"
                  :disabled="!canAddOlevelSitting"
                  @click="addOlevelSitting"
                >
                  {{
                    olevelDrafts.length
                      ? "＋ Add another exam sitting"
                      : "＋ Add O’Level result"
                  }}
                </button>
                <p v-if="isJuniorStaff" class="olevel-rule-note">
                  Junior Staff in the School of Nursing may enter one sitting;
                  other Junior Staff may enter up to two sittings per exam body.
                </p>
                <p v-else class="olevel-rule-note">
                  You may save partial details or leave this section empty.
                </p>
              </section>
            </div>
            <div v-else class="fields-grid">
              <div
                v-for="(f, j) in sections[profileTab].fields"
                :key="f"
                :class="{
                  'field-wide':
                    f.includes('address') || f === 'Permanent address',
                }"
              >
                <label
                  >{{ f }}
                  <i v-if="j < 2 || f === 'Date of first appointment'"
                    >*</i
                  ></label
                >
                <template v-if="lookupKey(f)">
                  <select
                    :value="profileData[f] || ''"
                    :disabled="
                      !!dependentStateField(f) &&
                      !profileData[dependentStateField(f)]
                    "
                    @change="setProfileDropdown(f, $event.target.value)"
                  >
                    <option value="" disabled>
                      {{ profileDropdownPlaceholder(f) }}
                    </option>
                    <option
                      v-for="choice in getOptions(f)"
                      :key="choice"
                      :value="choice"
                    >
                      {{ choice }}
                    </option>
                  </select>
                  <button
                    type="button"
                    class="add-personnel-option"
                    :disabled="
                      !!dependentStateField(f) &&
                      !profileData[dependentStateField(f)]
                    "
                    @click="startAddDropdown(f)"
                  >
                    Can’t find an option? Add it
                  </button>
                </template>
                <textarea
                  v-else-if="f.toLowerCase().includes('address')"
                  :value="profileData[f]"
                  @input="profileData[f] = $event.target.value"
                  :placeholder="`Enter ${f.toLowerCase()}`"
                ></textarea
                ><input
                  v-else-if="!lookupKey(f)"
                  :value="profileData[f]"
                  @input="profileData[f] = $event.target.value"
                  :type="
                    f.includes('Date')
                      ? 'date'
                      : f === 'Email'
                      ? 'email'
                      : f.includes('number')
                      ? 'tel'
                      : 'text'
                  "
                  :placeholder="`Enter ${f.toLowerCase()}`"
                />
              </div>
            </div>
            <div class="form-footer">
              <button
                class="back-btn"
                :disabled="profileTab === 0"
                @click="goToProfileSection(profileTab - 1)"
              >
                <v-icon icon="mdi-arrow-left" size="17" /> Back
              </button>
              <div>
                <button class="save-btn" @click="saveProfile(false)">
                  Save for later</button
                ><button class="primary-btn" @click="saveProfile(true)">
                  {{
                    profileTab < sections.length - 1
                      ? "Save & continue"
                      : "Submit profile"
                  }}
                  <v-icon
                    :icon="
                      profileTab < sections.length - 1
                        ? 'mdi-arrow-right'
                        : 'mdi-check'
                    "
                    size="17"
                  />
                </button>
              </div>
            </div>
          </div>
        </div>
        <article class="personnel-print-document" aria-hidden="true">
          <header class="print-document-header">
            <div class="print-uch-mark" role="img" aria-label="UCH logo"></div>
            <div>
              <span>UNIVERSITY COLLEGE HOSPITAL · IBADAN</span>
              <h1>Personnel Information Record</h1>
              <p>Personnel Information System</p>
            </div>
            <div class="print-record-stamp">
              <b>CONFIDENTIAL</b><small>PERSONNEL RECORD</small>
            </div>
          </header>
          <div class="print-person-summary">
            <div>
              <span>PERSONNEL RECORD</span>
              <h2>
                {{
                  [
                    profileData.Title,
                    profileData["First name"],
                    profileData["Other names"],
                    profileData.Surname,
                  ]
                    .filter(Boolean)
                    .join(" ") ||
                  store.user?.name ||
                  "Personnel profile"
                }}
              </h2>
              <p>
                {{ profileData.Department || "University College Hospital"
                }}<template v-if="profileData.Designation">
                  · {{ profileData.Designation }}</template
                >
              </p>
            </div>
            <div class="print-record-meta">
              <span
                >FILE NUMBER
                <b>{{ profileData["File number"] || "—" }}</b></span
              ><span
                >IPPIS NUMBER
                <b>{{ profileData["IPPIS number"] || "—" }}</b></span
              >
            </div>
          </div>
          <section
            v-for="group in printableSections"
            :key="group.title"
            class="print-data-section"
          >
            <h3>
              <span>{{ group.title }}</span
              ><i></i>
            </h3>
            <div class="print-data-grid">
              <div
                v-for="row in group.rows"
                :key="row.label"
                class="print-data-row"
              >
                <span>{{ row.label }}</span
                ><b>{{ row.value }}</b>
              </div>
            </div>
          </section>
          <footer class="print-document-footer">
            <span
              >Generated
              {{
                new Date().toLocaleDateString("en-NG", {
                  day: "numeric",
                  month: "long",
                  year: "numeric",
                })
              }}</span
            ><span>Private personnel information · Handle with care</span
            ><span>UCH · PEOPLE FIRST, ALWAYS</span>
          </footer>
        </article>
      </section>
      <section v-else class="content">
        <div class="welcome-row">
          <div>
            <div class="eyebrow">
              <h1>PERSONNEL INFORMATION SYSTEM</h1>
              <span class="eyebrow-slash">/</span> ADMINISTRATION
            </div>
            <h1>{{ nav }}</h1>
            <p class="subhead">
              {{
                nav === "Dropdown options"
                  ? "Shape the choices available in personnel profiles."
                  : nav === "Personnel invitations"
                  ? "Approve staff emails one at a time or prepare a CSV list."
                  : nav === "Messages"
                  ? "A private place for personnel to ask the People team for help."
                  : "Keep your people records accurate and up to date."
              }}
            </p>
          </div>
          <button
            v-if="
              nav === 'Overview'
                ? can('manage_invitations')
                : nav === 'Personnel directory'
                ? can('export_personnel')
                : nav === 'Dropdown options'
                ? can('manage_lookups')
                : false
            "
            class="primary-btn"
            @click="
              nav === 'Dropdown options'
                ? ((optionCategory = ''),
                  (optionLabel = ''),
                  (optionParent = ''),
                  (editingLookupId = null),
                  (showOptions = true))
                : nav === 'Personnel invitations'
                ? notify(
                    'Choose a CSV file to import approved email addresses.'
                  )
                : nav === 'Personnel directory'
                ? exportDirectory()
                : (showInvite = true)
            "
          >
            <v-icon
              :icon="
                nav === 'Personnel directory' ? 'mdi-download' : 'mdi-plus'
              "
              size="18"
            />
            {{
              nav === "Dropdown options"
                ? "Add an option"
                : nav === "Personnel invitations"
                ? "Upload CSV"
                : nav === "Personnel directory"
                ? "Export all to Excel"
                : "Add personnel"
            }}
          </button>
        </div>
        <div v-if="nav === 'Admin sign in'" class="admin-card login-card">
          <div class="dialog-symbol">
            <v-icon icon="mdi-shield-lock-outline" />
          </div>
          <h2>Administrator sign in</h2>
          <p>
            Use your seeded admin or superadmin account to manage and export
            personnel records.
          </p>
          <form @submit.prevent="adminSignIn">
            <label class="dialog-label">EMAIL ADDRESS</label
            ><input
              v-model="adminEmail"
              class="dialog-input"
              type="email"
              autocomplete="username"
              required
              placeholder="admin@example.org"
            /><label class="dialog-label">PASSWORD</label>
            <div class="dialog-password-wrap">
              <input
                v-model="adminPassword"
                class="dialog-input"
                :type="showAdminPassword ? 'text' : 'password'"
                autocomplete="current-password"
                required
                placeholder="Your administrator password"
              /><button
                class="password-toggle"
                type="button"
                :aria-label="
                  showAdminPassword ? 'Hide password' : 'Show password'
                "
                :aria-pressed="showAdminPassword"
                @click="showAdminPassword = !showAdminPassword"
              >
                <svg
                  v-if="!showAdminPassword"
                  class="password-eye-icon"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                  focusable="false"
                >
                  <path
                    d="M2.2 12s3.6-7 9.8-7 9.8 7 9.8 7-3.6 7-9.8 7-9.8-7-9.8-7Z"
                  />
                  <circle cx="12" cy="12" r="3" /></svg
                ><svg
                  v-else
                  class="password-eye-icon"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                  focusable="false"
                >
                  <path
                    d="m3 3 18 18M10.6 5.2A10.9 10.9 0 0 1 12 5c6.2 0 9.8 7 9.8 7a16 16 0 0 1-3.2 4.1M6.2 6.2C3.6 8.1 2.2 12 2.2 12s3.6 7 9.8 7c1.2 0 2.3-.3 3.3-.7"
                  />
                  <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                </svg>
              </button>
            </div>
            <div v-if="loginError" class="auth-error">{{ loginError }}</div>
            <button
              class="primary-btn dialog-submit"
              type="submit"
              :disabled="loginBusy"
            >
              {{ loginBusy ? "Signing in…" : "Sign in securely" }}
              <v-icon icon="mdi-arrow-right" size="17" />
            </button>
          </form>
          <small>Use the credentials configured in your .env file.</small>
        </div>
        <div v-if="nav === 'Dropdown options'" class="admin-card">
          <div class="admin-card-head">
            <div>
              <h2>Choices for the personnel form</h2>
              <p>
                Update the options staff see as they complete their records.
              </p>
            </div>
            <div class="lookup-filters">
              <label class="lookup-category-filter"
                ><span>Category</span
                ><select v-model="lookupCategoryFilter">
                  <option value="">All dropdowns</option>
                  <option
                    v-for="category in configurableCategories"
                    :key="category"
                    :value="category"
                  >
                    {{ categoryTitle(category) }}
                  </option>
                </select></label
              >
              <div class="search-box">
                <v-icon icon="mdi-magnify" size="18" /><input
                  v-model="lookupSearch"
                  placeholder="Search option names"
                />
              </div>
            </div>
          </div>
          <div class="lookup-import">
            <label
              ><input
                type="file"
                accept=".csv,text/csv"
                @change="uploadLookupFile"
              /><v-icon icon="mdi-file-table-outline" size="18" /><span
                ><b>Import options from CSV</b
                ><small>Columns: category, label, optional parent</small></span
              ></label
            ><span class="import-result" v-if="lookupImportResult">{{
              lookupImportResult
            }}</span
            ><a
              class="text-action"
              download="uch-personnel-options.csv"
              href="data:text/csv;charset=utf-8,category%2Clabel%2Cparent%0Asalary_structure%2CCONHESS%2C%0A"
              >Download template</a
            >
          </div>
          <div class="options-grid">
            <article
              v-for="(opts, name) in lookupGroups"
              :key="name"
              class="option-group"
              :class="{ expanded: lookupGroupIsOpen(name) }"
            >
              <div class="option-title">
                <button
                  type="button"
                  class="option-expand"
                  :aria-expanded="lookupGroupIsOpen(name)"
                  @click="toggleLookupGroup(name)"
                >
                  <v-icon
                    :icon="
                      lookupGroupIsOpen(name)
                        ? 'mdi-chevron-down'
                        : 'mdi-chevron-right'
                    "
                    size="19"
                  /><span
                    ><b>{{ categoryTitle(name) }}</b
                    ><small>{{
                      opts.length
                        ? `${opts.length} configured choices`
                        : "No choices yet"
                    }}</small></span
                  ></button
                ><button
                  type="button"
                  class="add-option"
                  @click="
                    optionCategory = name;
                    optionLabel = '';
                    optionParent = '';
                    editingLookupId = null;
                    showOptions = true;
                  "
                >
                  <v-icon icon="mdi-plus" size="16" /> Add option
                </button>
              </div>
              <div v-if="lookupGroupIsOpen(name)" class="chips">
                <span v-for="option in opts" :key="option.id"
                  ><span class="lookup-option-name">{{ option.label }}</span>
                  <small v-if="option.parent" class="lookup-parent">{{
                    option.parent
                  }}</small
                  ><span class="lookup-option-actions"
                    ><button
                      type="button"
                      :aria-label="`Edit ${option.label}`"
                      :title="`Edit ${option.label}`"
                      @click="editLookupOption(option)"
                    >
                      <svg
                        class="interface-icon"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        focusable="false"
                      >
                        <path
                          d="m15 5 4 4M4 20l4.2-.9L19 8.3a2.1 2.1 0 0 0-3-3L5.2 16.1 4 20Z"
                        />
                      </svg></button
                    ><button
                      type="button"
                      :aria-label="`Remove ${option.label} from choices`"
                      :title="`Remove ${option.label} from future choices`"
                      @click="deleteLookupOption(option)"
                    >
                      <svg
                        class="interface-icon"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        focusable="false"
                      >
                        <path d="m6 6 12 12M18 6 6 18" />
                      </svg></button></span
                ></span>
              </div>
            </article>
            <div v-if="!Object.keys(lookupGroups).length" class="empty-options">
              No active choices match that search.
            </div>
          </div>
        </div>
        <div
          v-else-if="nav === 'Personnel directory'"
          class="admin-card admin-directory"
        >
          <form class="directory-filters" @submit.prevent="fetchDirectory(1)">
            <label
              >Name<input
                v-model="directoryFilters.name"
                placeholder="Search names" /></label
            ><label
              >Email<input
                v-model="directoryFilters.email"
                placeholder="Search email" /></label
            ><label
              >IPPIS number<input
                v-model="directoryFilters.ippis_no"
                placeholder="Search IPPIS No." /></label
            ><label
              >File number<input
                v-model="directoryFilters.file_no"
                placeholder="Search File No." /></label
            ><button class="primary-btn" type="submit">
              <v-icon icon="mdi-magnify" size="17" /> Apply filters</button
            ><button
              class="clear-filters"
              type="button"
              @click="clearDirectoryFilters"
            >
              Clear
            </button>
          </form>
          <div class="admin-directory-meta">
            <span>{{ directoryTotal.toLocaleString() }} personnel records</span
            ><span
              >Excel export follows the current filters and excludes uploaded
              files.</span
            >
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>PERSONNEL</th>
                  <th>EMAIL</th>
                  <th>IPPIS NO.</th>
                  <th>FILE NO.</th>
                  <th>PROFILE STATUS</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in directoryRows" :key="row.id">
                  <td>
                    <b>{{
                      row.user?.name ||
                      [row.first_name, row.surname].filter(Boolean).join(" ") ||
                      "Name not provided"
                    }}</b>
                  </td>
                  <td>{{ row.user?.email || "—" }}</td>
                  <td>{{ row.ippis_number || "—" }}</td>
                  <td>{{ row.file_number || "—" }}</td>
                  <td>
                    <select
                      v-if="can('review_personnel')"
                      v-model="row.status"
                      aria-label="Profile status"
                      class="status-select"
                      @change="updatePersonnelStatus(row)"
                    >
                      <option value="incomplete">Incomplete</option>
                      <option value="in_review">In review</option>
                      <option value="complete">Complete</option></select
                    ><span v-else>{{ row.status }}</span>
                  </td>
                </tr>
                <tr v-if="!directoryRows.length">
                  <td colspan="5" class="empty-row">
                    {{
                      directoryBusy
                        ? "Loading personnel…"
                        : "No matching personnel records. Apply filters or sign in as an administrator."
                    }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="directory-pagination">
            <span
              >Page {{ directoryPage }} of
              {{ Math.max(1, Math.ceil(directoryTotal / 25)) }}</span
            >
            <div>
              <button
                :disabled="directoryPage <= 1 || directoryBusy"
                @click="fetchDirectory(directoryPage - 1)"
              >
                Previous</button
              ><button
                :disabled="
                  directoryPage >= Math.ceil(directoryTotal / 25) ||
                  directoryBusy
                "
                @click="fetchDirectory(directoryPage + 1)"
              >
                Next
              </button>
            </div>
          </div>
        </div>
        <div
          v-else-if="nav === 'Personnel invitations'"
          class="invitation-management"
        >
          <section class="invitation-panel">
            <div class="invitation-panel-icon">
              <v-icon icon="mdi-account-plus-outline" size="23" />
            </div>
            <span class="form-kicker">INDIVIDUAL APPROVAL</span>
            <h2>Add one personnel email</h2>
            <p>
              Approve a staff member’s email address. They can then create an
              account and verify it with a code sent to that inbox.
            </p>
            <form
              class="invitation-form"
              @submit.prevent="addInvitationFromPage"
            >
              <label for="personnel-invite-email">Personal email address</label
              ><input
                id="personnel-invite-email"
                v-model="inviteEmail"
                type="email"
                autocomplete="email"
                maxlength="254"
                required
                placeholder="name@example.com"
              /><button
                class="primary-btn"
                type="submit"
                :disabled="invitationPageBusy || !inviteEmail.trim()"
              >
                <v-icon icon="mdi-check-circle-outline" size="18" />{{
                  invitationPageBusy ? "Approving email…" : "Approve email"
                }}
              </button>
            </form>
            <p
              v-if="invitationPageMessage"
              class="import-result invitation-feedback"
            >
              {{ invitationPageMessage }}
            </p>
          </section>
          <section class="invitation-panel">
            <div class="invitation-panel-icon">
              <v-icon icon="mdi-email-multiple-outline" size="23" />
            </div>
            <span class="form-kicker">BULK APPROVAL</span>
            <h2>Import a personnel email list</h2>
            <p>
              Upload a CSV file with one email address in the first column. A
              header row named “email” is optional. Up to 10,000 rows are
              accepted.
            </p>
            <label class="upload-area import-upload"
              ><input
                type="file"
                accept=".csv,text/csv"
                @change="uploadInvitationFile"
              /><v-icon icon="mdi-cloud-upload-outline" size="24" /><b>{{
                bulkFile ? bulkFile.name : "Choose a CSV file to upload"
              }}</b
              ><small
                >CSV only · One email per row · Up to 10,000 records</small
              ></label
            >
            <p v-if="bulkResult" class="import-result invitation-feedback">
              {{ bulkResult }}
            </p>
            <a
              class="text-action"
              download="uch-personnel-emails.csv"
              href="data:text/csv;charset=utf-8,email%0A"
              >Download CSV template <v-icon icon="mdi-download" size="16"
            /></a>
          </section>
        </div>
        <div
          v-else-if="nav === 'Messages'"
          class="chat-workspace"
          :class="{
            'personnel-chat-workspace': !['admin', 'superadmin'].includes(
              store.user?.role
            ),
          }"
        >
          <aside
            v-if="['admin', 'superadmin'].includes(store.user?.role)"
            class="chat-inbox"
          >
            <div class="chat-inbox-heading">
              <span class="form-kicker">PERSONNEL INFORMATION SYSTEM</span>
              <h2>Conversations</h2>
              <p>Private enquiries with UCH personnel.</p>
            </div>
            <form class="chat-search" @submit.prevent="loadChat()">
              <input
                v-model="chatSearch"
                aria-label="Search personnel"
                placeholder="Find a colleague by name or email"
              /><button class="primary-btn" type="submit">Search</button>
            </form>
            <div class="chat-contact-list">
              <button
                v-for="person in chatPeople"
                :key="person.id"
                type="button"
                class="chat-contact"
                :class="{ selected: chatSelectedPerson?.id === person.id }"
                @click="selectChatPerson(person)"
              >
                <span class="chat-avatar">{{
                  (person.name || "UC")
                    .split(" ")
                    .map((part) => part[0])
                    .join("")
                    .slice(0, 2)
                    .toUpperCase()
                }}</span
                ><span class="chat-contact-copy"
                  ><b>{{ person.name }}</b
                  ><small>{{
                    person.last_message || person.email
                  }}</small></span
                ><span
                  v-if="chatSelectedPerson?.id === person.id"
                  class="chat-active-dot"
                ></span>
              </button>
              <p v-if="!chatPeople.length" class="chat-empty">
                No conversations yet. Search for a colleague to start one.
              </p>
            </div>
          </aside>
          <aside v-else class="chat-inbox personnel-chat-inbox">
            <div class="chat-inbox-heading">
              <span class="form-kicker">UCH PERSONNEL SERVICES</span>
              <h2>Your support inbox</h2>
              <p>A direct, private line to your People team.</p>
            </div>
            <div class="chat-contact-list">
              <button
                type="button"
                class="chat-contact selected personnel-service-contact"
                aria-current="page"
              >
                <span class="chat-avatar personnel-service-avatar">
                  <v-icon icon="mdi-headset" size="21" /> </span
                ><span class="chat-contact-copy"
                  ><b>Personnel Services</b
                  ><small>People team · UCH Ibadan</small></span
                ><span class="chat-active-dot" aria-label="Available"></span>
              </button>
            </div>
            <div class="personnel-chat-note">
              <span class="personnel-note-icon">
                <v-icon icon="mdi-shield-lock-outline" size="19" />
              </span>
              <div class="personnel-note-copy">
                <span class="personnel-note-kicker">PRIVATE CHANNEL</span>
                <b>Your conversation stays confidential</b>
                <p>
                  Only you and authorised Personnel Services administrators can
                  read these messages.
                </p>
              </div>
            </div>
          </aside>
          <section class="chat-panel">
            <template
              v-if="
                !['admin', 'superadmin'].includes(store.user?.role) ||
                chatSelectedPerson
              "
              ><header class="chat-header">
                <span class="chat-avatar">{{
                  (["admin", "superadmin"].includes(store.user?.role)
                    ? chatSelectedPerson?.name
                    : "Personnel Services"
                  )
                    ?.split(" ")
                    .map((part) => part[0])
                    .join("")
                    .slice(0, 2)
                    .toUpperCase() || "PS"
                }}</span>
                <div>
                  <b>{{
                    ["admin", "superadmin"].includes(store.user?.role)
                      ? chatSelectedPerson?.name
                      : "Personnel Services"
                  }}</b
                  ><small>{{
                    ["admin", "superadmin"].includes(store.user?.role)
                      ? chatSelectedPerson?.email
                      : "Ask a question or request help from the HR team"
                  }}</small>
                </div>
                <span class="chat-live-label"
                  ><i></i
                  >{{
                    chatRealtimeStatus === "connected"
                      ? "Live now"
                      : chatRealtimeStatus === "connecting"
                      ? "Connecting…"
                      : "Auto refresh"
                  }}</span
                >
              </header>
              <div class="chat-messages" aria-live="polite">
                <div v-if="!chatMessages.length" class="chat-start">
                  <span class="chat-start-icon"
                    ><v-icon icon="mdi-message-text-outline" size="24" /></span
                  ><b>How can we help?</b>
                  <p>
                    Send a message to the ITD Team. Your enquiry and their reply
                    will stay here.
                  </p>
                </div>
                <article
                  v-for="message in chatMessages"
                  :key="message.id"
                  class="chat-message"
                  :class="{ mine: message.sender_id === store.user?.id }"
                >
                  <small>{{
                    message.sender_id === store.user?.id
                      ? "You"
                      : message.sender_name
                  }}</small>
                  <p>{{ message.body }}</p>
                  <time>{{
                    new Date(message.created_at).toLocaleString("en-NG", {
                      day: "numeric",
                      month: "short",
                      hour: "numeric",
                      minute: "2-digit",
                    })
                  }}</time>
                </article>
              </div>
              <p v-if="chatError" class="chat-error">{{ chatError }}</p>
              <form class="chat-composer" @submit.prevent="sendChatMessage">
                <textarea
                  v-model="chatDraft"
                  rows="2"
                  maxlength="5000"
                  aria-label="Write your message"
                  :placeholder="
                    ['admin', 'superadmin'].includes(store.user?.role)
                      ? `Write a private reply to ${
                          chatSelectedPerson?.name || 'this colleague'
                        }…`
                      : 'Write your message to Personnel Services…'
                  "
                  @keydown.enter.exact.prevent="sendChatMessage"
                ></textarea
                ><button
                  type="submit"
                  class="primary-btn"
                  :disabled="chatSending || !chatDraft.trim()"
                >
                  {{ chatSending ? "Sending…" : "Send message" }}
                  <v-icon icon="mdi-arrow-right" size="17" /></button
                ><small>{{
                  ["admin", "superadmin"].includes(store.user?.role)
                    ? "Your reply is private to this personnel conversation."
                    : "Messages are private to you and authorised Personnel Services administrators."
                }}</small>
              </form></template
            >
            <div v-else class="chat-pick-person">
              <span class="chat-start-icon"
                ><v-icon icon="mdi-message-text-outline" size="26"
              /></span>
              <h2>Choose a colleague</h2>
              <p>Select a person from the list to open your conversation.</p>
            </div>
          </section>
        </div>
        <div
          v-else-if="nav === 'Access & roles'"
          class="admin-card admin-account-card"
        >
          <div>
            <span class="auth-overline">ACCESS &amp; ROLES</span>
            <h2>Administrator accounts</h2>
            <p>
              Give trusted HR colleagues access to manage personnel records.
              Only a superadmin can create another administrator.
            </p>
          </div>
          <form
            v-if="store.user?.role === 'superadmin'"
            class="admin-create-form"
            @submit.prevent="createAdministrator"
          >
            <label
              >FULL NAME<input
                v-model="newAdmin.name"
                required
                maxlength="120"
                autocomplete="name"
                placeholder="Colleague’s name" /></label
            ><label
              >WORK EMAIL<input
                v-model="newAdmin.email"
                required
                type="email"
                autocomplete="email"
                placeholder="name@uch-ibadan.org.ng" /></label
            ><label
              >STRONG PASSWORD<input
                v-model="newAdmin.password"
                required
                type="password"
                minlength="14"
                autocomplete="new-password"
                placeholder="At least 14 characters, with upper/lowercase, number and symbol" /></label
            ><label
              >CONFIRM PASSWORD<input
                v-model="newAdmin.password_confirmation"
                required
                type="password"
                minlength="14"
                autocomplete="new-password"
                placeholder="Enter the same password again"
            /></label>
            <div>
              <span class="dialog-label">ACCESS PERMISSIONS</span>
              <div class="permission-list">
                <label
                  v-for="item in adminPermissionOptions"
                  :key="item.key"
                  class="permission-choice"
                  ><input
                    v-model="newAdmin.permissions"
                    type="checkbox"
                    :value="item.key"
                  /><span
                    ><b>{{ item.label }}</b
                    ><small>{{ item.description }}</small></span
                  ></label
                >
              </div>
            </div>
            <p v-if="adminCreateMessage" class="import-result">
              {{ adminCreateMessage }}
            </p>
            <button
              class="primary-btn"
              type="submit"
              :disabled="adminCreateBusy"
            >
              {{
                adminCreateBusy ? "Creating account…" : "Create administrator"
              }}
              <v-icon icon="mdi-account-plus-outline" size="17" />
            </button>
          </form>
          <p v-if="adminAccountsMessage" class="import-result">
            {{ adminAccountsMessage }}
          </p>
          <div v-if="store.user?.role === 'superadmin'" class="admin-list">
            <div
              v-for="account in adminAccounts"
              :key="account.id"
              class="admin-list-row"
            >
              <div>
                <b>{{ account.name }}</b
                ><small>{{ account.email }}</small>
              </div>
              <div class="admin-access-editor">
                <div class="permission-list">
                  <label
                    v-for="item in adminPermissionOptions"
                    :key="item.key"
                    class="permission-choice"
                    ><input
                      v-model="account.permissions"
                      type="checkbox"
                      :value="item.key"
                    /><span
                      ><b>{{ item.label }}</b></span
                    ></label
                  >
                </div>
                <button
                  class="primary-btn"
                  type="button"
                  :disabled="adminPermissionsBusy"
                  @click="saveAdminPermissions(account)"
                >
                  {{ adminPermissionsBusy ? "Saving…" : "Save access" }}
                </button>
              </div>
            </div>
            <p v-if="!adminAccounts.length" class="empty-options">
              No administrator accounts are available yet.
            </p>
          </div>
          <div v-else class="dialog-tip">
            <v-icon icon="mdi-lock-outline" size="17" /> Your current
            administrator role can manage staff records and form options.
            Contact the superadmin to create new admin accounts.
          </div>
        </div>
        <div v-else class="admin-card settings-card">
          <div
            v-for="(item, i) in [
              'Organisation profile',
              'Personnel email invitations',
              'Two-step sign-in',
              'Administrator access',
            ]"
            :key="item"
            class="setting-row"
          >
            <span class="setting-symbol"
              ><v-icon
                :icon="
                  [
                    'mdi-office-building-outline',
                    'mdi-email-check-outline',
                    'mdi-shield-lock-outline',
                    'mdi-account-key-outline',
                  ][i]
                "
            /></span>
            <div>
              <b>{{ item }}</b
              ><small>{{
                [
                  "Name, region and contact details",
                  "Approved emails and invitation status",
                  "Email verification codes for every new account",
                  "Superadmin and admin roles",
                ][i]
              }}</small>
            </div>
            <v-icon icon="mdi-chevron-right" />
          </div>
        </div>
      </section>
      <footer class="footer">
        <span
          >© 2026 University College Hospital · Personnel Information
          System</span
        ><span
          ><v-icon icon="mdi-lock-outline" size="13" /> Your information is
          protected</span
        >
      </footer>
    </main>
    <Teleport to="body"
      ><div
        v-if="showInvite"
        class="pis-modal-backdrop"
        role="presentation"
        @click.self="showInvite = false"
      >
        <section
          class="dialog-card pis-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="invite-dialog-title"
        >
          <div class="dialog-top">
            <div class="dialog-symbol">
              <v-icon icon="mdi-account-plus-outline" />
            </div>
            <button
              type="button"
              class="dialog-close"
              aria-label="Close dialog"
              title="Close"
              @click="showInvite = false"
            >
              <span aria-hidden="true">×</span>
            </button>
          </div>
          <h2 id="invite-dialog-title">Invite a colleague</h2>
          <p>
            Add their approved personal email. They can use this approved
            address to receive a one-time code and create their account.
          </p>
          <label class="dialog-label">PERSONAL EMAIL ADDRESS</label
          ><input
            v-model="inviteEmail"
            class="dialog-input"
            type="email"
            required
            placeholder="name@example.com"
          />
          <div class="dialog-tip">
            <v-icon icon="mdi-information-outline" size="17" /> Only
            pre-approved email addresses can be used to sign up.
          </div>
          <button class="primary-btn dialog-submit" @click="addInvitation">
            Continue <v-icon icon="mdi-arrow-right" size="17" /></button
          ><button
            class="signup-link"
            @click="
              showInvite = false;
              showSignup = true;
              signupStep = 0;
              signupError = '';
            "
          >
            Are you personnel? <b>Create your account</b>
          </button>
        </section>
      </div></Teleport
    ><Teleport to="body"
      ><div
        v-if="showSignup"
        class="pis-modal-backdrop"
        role="presentation"
        @click.self="showSignup = false"
      >
        <section
          class="dialog-card pis-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="signup-dialog-title"
        >
          <div class="dialog-top">
            <div class="dialog-symbol">
              <v-icon icon="mdi-shield-check-outline" />
            </div>
            <button
              type="button"
              class="dialog-close"
              aria-label="Close dialog"
              title="Close"
              @click="showSignup = false"
            >
              <span aria-hidden="true">×</span>
            </button>
          </div>
          <div class="form-kicker">
            SECURE PERSONNEL SIGN-UP · STEP {{ signupStep + 1 }} OF 3
          </div>
          <h2 id="signup-dialog-title">
            {{
              signupStep === 0
                ? "Check your approved email"
                : signupStep === 1
                ? "Create your password"
                : "Verify your email"
            }}
          </h2>
          <p>
            {{
              signupStep === 0
                ? "Your personal email must be on the approved list from your personnel administrator."
                : signupStep === 1
                ? "Choose a strong password to protect your personnel information."
                : "Enter the six-digit code sent to " +
                  signupEmail +
                  ". The code expires in 10 minutes."
            }}
          </p>
          <label
            v-if="signupStep === 0 || signupStep === 1"
            class="dialog-label"
            >PERSONAL EMAIL ADDRESS</label
          ><input
            v-if="signupStep === 0 || signupStep === 1"
            v-model="signupEmail"
            class="dialog-input"
            type="email"
            placeholder="name@example.com"
          /><template v-if="signupStep === 1"
            ><label class="dialog-label"
              >PASSWORD · 12 CHARACTERS MINIMUM</label
            >
            <div class="dialog-password-wrap">
              <input
                v-model="signupPassword"
                class="dialog-input"
                :type="showSignupPassword ? 'text' : 'password'"
                autocomplete="new-password"
                placeholder="Create a password"
              /><button
                class="password-toggle"
                type="button"
                :aria-label="
                  showSignupPassword ? 'Hide password' : 'Show password'
                "
                :aria-pressed="showSignupPassword"
                @click="showSignupPassword = !showSignupPassword"
              >
                <svg
                  v-if="!showSignupPassword"
                  class="password-eye-icon"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                  focusable="false"
                >
                  <path
                    d="M2.2 12s3.6-7 9.8-7 9.8 7 9.8 7-3.6 7-9.8 7-9.8-7-9.8-7Z"
                  />
                  <circle cx="12" cy="12" r="3" /></svg
                ><svg
                  v-else
                  class="password-eye-icon"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                  focusable="false"
                >
                  <path
                    d="m3 3 18 18M10.6 5.2A10.9 10.9 0 0 1 12 5c6.2 0 9.8 7 9.8 7a16 16 0 0 1-3.2 4.1M6.2 6.2C3.6 8.1 2.2 12 2.2 12s3.6 7 9.8 7c1.2 0 2.3-.3 3.3-.7"
                  />
                  <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                </svg>
              </button>
            </div>
            <label class="dialog-label">CONFIRM PASSWORD</label>
            <div class="dialog-password-wrap">
              <input
                v-model="signupConfirm"
                class="dialog-input"
                :type="showSignupConfirm ? 'text' : 'password'"
                autocomplete="new-password"
                placeholder="Enter it again"
              /><button
                class="password-toggle"
                type="button"
                :aria-label="
                  showSignupConfirm
                    ? 'Hide password confirmation'
                    : 'Show password confirmation'
                "
                :aria-pressed="showSignupConfirm"
                @click="showSignupConfirm = !showSignupConfirm"
              >
                <svg
                  v-if="!showSignupConfirm"
                  class="password-eye-icon"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                  focusable="false"
                >
                  <path
                    d="M2.2 12s3.6-7 9.8-7 9.8 7 9.8 7-3.6 7-9.8 7-9.8-7-9.8-7Z"
                  />
                  <circle cx="12" cy="12" r="3" /></svg
                ><svg
                  v-else
                  class="password-eye-icon"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                  focusable="false"
                >
                  <path
                    d="m3 3 18 18M10.6 5.2A10.9 10.9 0 0 1 12 5c6.2 0 9.8 7 9.8 7a16 16 0 0 1-3.2 4.1M6.2 6.2C3.6 8.1 2.2 12 2.2 12s3.6 7 9.8 7c1.2 0 2.3-.3 3.3-.7"
                  />
                  <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                </svg>
              </button></div></template
          ><template v-if="signupStep === 2"
            ><label class="dialog-label">VERIFICATION CODE</label
            ><input
              v-model="signupCode"
              class="dialog-input otp-input"
              inputmode="numeric"
              maxlength="6"
              placeholder="000000"
          /></template>
          <div v-if="signupError" class="auth-error">{{ signupError }}</div>
          <div class="dialog-tip">
            <v-icon icon="mdi-lock-outline" size="17" /> Your details are
            protected and only visible to authorised personnel staff.
          </div>
          <button
            class="primary-btn dialog-submit"
            :disabled="signupBusy"
            @click="beginSignup"
          >
            {{ signupStep === 2 ? "Verify & continue" : "Continue" }}
            <v-icon
              :icon="signupStep === 2 ? 'mdi-check' : 'mdi-arrow-right'"
              size="17"
            /></button
          ><button
            class="signup-link"
            @click="
              showSignup = false;
              showInvite = true;
            "
          >
            Need an invitation? Contact your administrator
          </button>
        </section>
      </div></Teleport
    ><Teleport to="body"
      ><div
        v-if="showOptions"
        class="pis-modal-backdrop"
        role="presentation"
        @click.self="showOptions = false"
      >
        <section
          class="dialog-card pis-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="options-dialog-title"
        >
          <div class="dialog-top">
            <div class="dialog-symbol">
              <v-icon icon="mdi-format-list-bulleted" />
            </div>
            <button
              type="button"
              class="dialog-close"
              aria-label="Close dialog"
              title="Close"
              @click="showOptions = false"
            >
              <span aria-hidden="true">×</span>
            </button>
          </div>
          <h2 id="options-dialog-title">
            {{ editingLookupId ? "Edit a form option" : "Add a form option" }}
          </h2>
          <p>Keep profile choices relevant and current for your people.</p>
          <form @submit.prevent="saveLookupOption">
            <label class="dialog-label">CATEGORY</label
            ><select
              v-model="optionCategory"
              class="dialog-input"
              :disabled="!!editingLookupId"
              required
              @change="optionParent = ''"
            >
              <option value="" disabled>Choose a category</option>
              <option
                v-for="category in configurableCategories"
                :key="category"
                :value="category"
              >
                {{ categoryTitle(category) }}
              </option></select
            ><template v-if="optionCategory === 'local_government'"
              ><label class="dialog-label">STATE</label
              ><select v-model="optionParent" class="dialog-input" required>
                <option value="" disabled>Choose the state</option>
                <option
                  v-for="state in getOptions('State of residence')"
                  :key="state"
                  :value="state"
                >
                  {{ state }}
                </option>
              </select></template
            ><label class="dialog-label">OPTION NAME</label
            ><input
              v-model="optionLabel"
              class="dialog-input"
              required
              maxlength="120"
              placeholder="e.g. Procurement"
            /><button class="primary-btn dialog-submit" type="submit">
              {{ editingLookupId ? "Save changes" : "Save option" }}
              <v-icon icon="mdi-check" size="17" />
            </button>
          </form>
        </section></div
    ></Teleport>
  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;1,9..144,500&family=Manrope:wght@400;500;600;700;800&display=swap');

/* =====================================================================
   UCH Personnel Information System · visual layer only
   Pine + emerald, brass hairlines, mist surfaces, Fraunces headings.
   Covers: sign-in/sign-up, app shell, overview, profile form, O'Level,
   admin tools, messages, modals, SweetAlert and the printable record.
   Tokens live on :root because modals and SweetAlert render in <body>.
   ===================================================================== */
:root {
  --pine-950: #061f1a;
  --pine-900: #082e27;
  --pine-800: #0c3f35;
  --pine-700: #115446;
  --emerald: #178465;
  --emerald-deep: #0f6a51;
  --emerald-tint: #e6f3ed;
  --brass: #c2a15f;
  --brass-soft: #ecd9ae;
  --mist: #eef4f1;
  --paper: #fbfcfb;
  --ink: #12261f;
  --ink-soft: #4b5f57;
  --ink-faint: #7d8f86;
  --line: #dbe6e0;
  --line-strong: #c3d4cb;
  --ring: rgb(23 132 101 / 16%);
  --blue: #2b62a8;
  --blue-tint: #e6effa;
  --lilac: #6353b0;
  --lilac-tint: #efecfa;
  --amber: #a8680f;
  --amber-tint: #fdf3dc;
  --red: #b42318;
  --red-tint: #fdecea;
  --shadow-sm: 0 1px 2px rgb(8 46 39 / 5%), 0 10px 26px -14px rgb(8 46 39 / 14%);
  --shadow-lg: 0 2px 6px rgb(8 46 39 / 6%), 0 34px 80px -24px rgb(8 46 39 / 34%);
  --font-display: 'Fraunces', 'Iowan Old Style', Georgia, serif;
  --font-body: 'Manrope', 'Inter', 'Segoe UI', system-ui, sans-serif;
  --sidebar-w: 276px;
}
body { margin: 0; background: var(--mist); }

.auth-shell,
.app-shell,
.pis-modal-backdrop {
  color: var(--ink);
  font-family: var(--font-body);
  font-size: 14px;
  line-height: 1.55;
  -webkit-font-smoothing: antialiased;
}
.auth-shell *, .auth-shell *::before, .auth-shell *::after,
.app-shell *, .app-shell *::before, .app-shell *::after,
.pis-modal-backdrop *, .pis-modal-backdrop *::before, .pis-modal-backdrop *::after { box-sizing: border-box; }
.auth-shell button, .auth-shell input, .auth-shell select, .auth-shell textarea,
.app-shell button, .app-shell input, .app-shell select, .app-shell textarea,
.pis-modal-backdrop button, .pis-modal-backdrop input, .pis-modal-backdrop select { font-family: inherit; }
.app-shell h1, .app-shell h2, .app-shell h3, .app-shell p,
.auth-shell h1, .auth-shell h2, .auth-shell p,
.pis-modal-backdrop h2, .pis-modal-backdrop p { margin-top: 0; }
.auth-shell svg, .app-shell svg, .pis-modal-backdrop svg { flex: none; }
.auth-shell button:focus-visible, .app-shell button:focus-visible, .app-shell a:focus-visible,
.pis-modal-backdrop button:focus-visible { outline: 3px solid var(--ring); outline-offset: 2px; }

/* ---------- Shared field styling ---------- */
.auth-input-wrap input,
.fields-grid input, .fields-grid select, .fields-grid textarea,
.dialog-input, .invitation-form input, .admin-create-form input,
.directory-filters input, .lookup-category-filter select,
.inline-option-add input, .status-select, .olevel-subject-row input, .olevel-subject-row select {
  width: 100%;
  min-width: 0;
  min-height: 48px;
  padding: 11px 14px;
  border: 1px solid var(--line);
  border-radius: 12px;
  outline: none;
  background-color: var(--paper);
  color: var(--ink);
  font-size: 14px;
  font-weight: 500;
  transition: border-color 0.2s, box-shadow 0.2s, background-color 0.2s;
}
.fields-grid textarea { min-height: 96px; line-height: 1.6; resize: vertical; }
.fields-grid select, .dialog-input[class] select, select.dialog-input, .lookup-category-filter select,
.status-select, .olevel-subject-row select, .education-qualification select {
  padding-right: 40px;
  appearance: none;
  cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23178465' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
  background-position: right 15px center;
  background-repeat: no-repeat;
}
.fields-grid select:disabled, .fields-grid input:disabled { opacity: 0.6; cursor: not-allowed; }
.fields-grid input[readonly] { background-color: var(--mist); color: var(--ink-soft); }
input::placeholder, textarea::placeholder { color: #a3b1aa; font-weight: 400; }
.fields-grid input:hover:not(:focus):not(:disabled), .fields-grid select:hover:not(:focus):not(:disabled), .fields-grid textarea:hover:not(:focus),
.dialog-input:hover:not(:focus) { border-color: var(--line-strong); }
.fields-grid input:focus, .fields-grid select:focus, .fields-grid textarea:focus,
.dialog-input:focus, .invitation-form input:focus, .admin-create-form input:focus, .directory-filters input:focus,
.lookup-category-filter select:focus, .inline-option-add input:focus, .status-select:focus,
.olevel-subject-row input:focus, .olevel-subject-row select:focus,
.auth-input-wrap:focus-within { border-color: var(--emerald); background-color: #fff; box-shadow: 0 0 0 4px var(--ring); }

/* ---------- Shared buttons ---------- */
.primary-btn, .auth-submit, .dialog-submit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  min-height: 46px;
  padding: 11px 22px;
  border: 0;
  border-radius: 12px;
  background: linear-gradient(180deg, #1a8e6d, var(--emerald-deep));
  box-shadow: inset 0 1px 0 rgb(255 255 255 / 20%), 0 1px 2px rgb(8 46 39 / 20%), 0 12px 24px -10px rgb(15 106 81 / 55%);
  color: #fff;
  cursor: pointer;
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.01em;
  white-space: nowrap;
  transition: transform 0.2s, filter 0.2s;
}
.primary-btn:hover:not(:disabled), .auth-submit:hover:not(:disabled), .dialog-submit:hover:not(:disabled) { transform: translateY(-1px); filter: brightness(1.06); }
.primary-btn:disabled, .auth-submit:disabled, .dialog-submit:disabled { opacity: 0.55; cursor: not-allowed; box-shadow: none; }
.save-btn, .back-btn, .filter-btn, .more-btn, .clear-filters, .clear-section-button, .add-education, .olevel-add-subject, .olevel-add-exam,
.directory-pagination button, .add-option {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 42px;
  padding: 9px 16px;
  border: 1px solid var(--line-strong);
  border-radius: 12px;
  background: #fff;
  color: var(--pine-800);
  cursor: pointer;
  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
  transition: background-color 0.2s, border-color 0.2s;
}
.save-btn:hover:not(:disabled), .back-btn:hover:not(:disabled), .filter-btn:hover, .more-btn:hover, .clear-filters:hover,
.clear-section-button:hover:not(:disabled), .add-education:hover, .olevel-add-subject:hover:not(:disabled), .olevel-add-exam:hover:not(:disabled),
.directory-pagination button:hover:not(:disabled), .add-option:hover { border-color: var(--emerald); background: var(--emerald-tint); }
.save-btn:disabled, .back-btn:disabled, .clear-section-button:disabled, .olevel-add-subject:disabled, .olevel-add-exam:disabled,
.directory-pagination button:disabled { opacity: 0.45; cursor: not-allowed; }
.add-education, .olevel-add-exam, .olevel-add-subject { border-style: dashed; color: var(--emerald-deep); width: 100%; }
.clear-section-button { color: var(--red); border-color: #f1c6c1; }
.clear-section-button:hover:not(:disabled) { border-color: var(--red); background: var(--red-tint); }
.text-action { display: inline-flex; align-items: center; gap: 6px; padding: 6px 2px; border: 0; background: none; color: var(--emerald-deep); cursor: pointer; font-size: 13px; font-weight: 700; text-decoration: none; }
.text-action:hover { text-decoration: underline; text-underline-offset: 4px; }

/* =====================================================================
   Sign in / create account
   ===================================================================== */
.auth-shell { min-height: 100vh; min-height: 100dvh; background: var(--mist); }
.auth-frame { display: grid; grid-template-columns: minmax(0, 1.02fr) minmax(0, 0.98fr); min-height: 100vh; min-height: 100dvh; }
.auth-story {
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 40px;
  padding: clamp(32px, 5vw, 72px);
  overflow: hidden;
  color: #fff;
  background:
    radial-gradient(ellipse 70% 50% at 0% 100%, rgb(94 190 150 / 20%), transparent 60%),
    radial-gradient(ellipse 60% 45% at 100% 0%, rgb(255 255 255 / 9%), transparent 60%),
    linear-gradient(155deg, var(--pine-900) 0%, var(--pine-800) 45%, var(--pine-700) 100%);
}
.story-orbit { position: absolute; border: 1px solid rgb(255 255 255 / 9%); border-radius: 50%; pointer-events: none; }
.orbit-one { right: -300px; bottom: -320px; width: 620px; height: 620px; box-shadow: 0 0 0 70px rgb(255 255 255 / 2%), 0 0 0 71px rgb(255 255 255 / 7%), 0 0 0 150px rgb(255 255 255 / 1.5%), 0 0 0 151px rgb(255 255 255 / 5%); }
.orbit-two { top: -110px; left: -110px; width: 260px; height: 260px; border-color: rgb(194 161 95 / 28%); }
.auth-story-brand { position: relative; z-index: 1; display: flex; align-items: center; gap: 14px; }
.auth-story-brand img { width: 56px; height: 56px; padding: 6px; border-radius: 16px; background: #fff; box-shadow: 0 0 0 5px rgb(255 255 255 / 8%), 0 14px 32px rgb(0 0 0 / 25%); object-fit: contain; }
.auth-story-brand b { display: block; font-size: 15px; font-weight: 700; }
.auth-story-brand small { display: block; margin-top: 2px; color: rgb(255 255 255 / 55%); font-size: 11px; font-weight: 700; letter-spacing: 0.22em; }
.story-content { position: relative; z-index: 1; max-width: 540px; }
.story-content h1 { margin: 0 0 20px; font-family: var(--font-display); font-size: clamp(34px, 3.8vw, 54px); font-weight: 500; letter-spacing: 0.005em; line-height: 1.1; text-wrap: balance; }
.story-content > p { max-width: 440px; margin: 0 0 34px; color: var(--brass-soft); font-family: var(--font-display); font-size: clamp(17px, 1.6vw, 21px); font-style: italic; line-height: 1.55; }
.story-points { display: grid; max-width: 440px; border-top: 1px solid rgb(255 255 255 / 12%); }
.story-points > div { display: flex; align-items: flex-start; gap: 14px; padding: 16px 0; border-bottom: 1px solid rgb(255 255 255 / 12%); }
.story-points > div > span { display: grid; flex: none; place-items: center; width: 34px; height: 34px; border-radius: 11px; background: rgb(194 161 95 / 16%); box-shadow: inset 0 0 0 1px rgb(194 161 95 / 50%); color: var(--brass-soft); }
.story-points b { display: block; font-size: 14.5px; font-weight: 700; }
.story-points small { display: block; margin-top: 2px; color: rgb(255 255 255 / 62%); font-size: 13px; line-height: 1.6; }
.story-foot { position: relative; z-index: 1; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; color: rgb(255 255 255 / 50%); font-size: 11px; font-weight: 700; letter-spacing: 0.16em; }

.auth-main { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 22px; padding: 40px clamp(20px, 5vw, 80px); background: radial-gradient(ellipse 80% 50% at 100% 0%, rgb(23 132 101 / 7%), transparent 60%), linear-gradient(180deg, #f6faf8, var(--mist)); }
.auth-mobile-brand { display: none; align-items: center; gap: 12px; color: var(--pine-900); font-family: var(--font-display); font-size: 18px; font-weight: 500; }
.auth-mobile-brand img { width: 40px; height: 40px; padding: 5px; border-radius: 12px; background: #fff; box-shadow: 0 0 0 1px var(--line); object-fit: contain; }
.auth-card { position: relative; width: 100%; max-width: 480px; padding: clamp(24px, 3.2vw, 42px); border: 1px solid rgb(255 255 255 / 80%); border-radius: 28px; background: rgb(255 255 255 / 94%); box-shadow: 0 0 0 1px var(--line), 0 2px 4px rgb(8 46 39 / 4%), 0 30px 70px -20px rgb(8 46 39 / 22%); animation: pis-rise 0.55s cubic-bezier(0.2, 0.7, 0.2, 1) both; }
.auth-card::before { position: absolute; top: -1px; left: 40px; right: 40px; height: 2px; content: ""; background: linear-gradient(90deg, transparent, var(--brass), transparent); }
@keyframes pis-rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
.auth-tabs { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; margin-bottom: 26px; padding: 4px; border: 1px solid var(--line); border-radius: 15px; background: var(--paper); }
.auth-tabs button { min-height: 42px; padding: 9px 12px; border: 0; border-radius: 11px; background: transparent; color: var(--ink-soft); cursor: pointer; font-size: 13.5px; font-weight: 600; transition: background-color 0.2s, color 0.2s; }
.auth-tabs button:hover { color: var(--pine-800); }
.auth-tabs button.selected { background: linear-gradient(150deg, var(--pine-900), var(--pine-700)); box-shadow: 0 8px 18px -8px rgb(8 46 39 / 55%); color: #fff; font-weight: 700; }
.auth-heading { margin-bottom: 24px; }
.auth-overline, .form-kicker { display: inline-flex; align-items: center; gap: 9px; color: var(--emerald-deep); font-size: 11.5px; font-weight: 800; letter-spacing: 0.1em; }
.auth-overline::before, .form-kicker::before { width: 18px; height: 2px; content: ""; border-radius: 2px; background: var(--brass); }
.auth-heading h2 { margin: 12px 0 8px; color: var(--pine-900); font-family: var(--font-display); font-size: clamp(28px, 3vw, 36px); font-weight: 500; letter-spacing: -0.02em; line-height: 1.12; }
.auth-heading p { margin: 0; color: var(--ink-soft); font-size: 14px; line-height: 1.7; }
.auth-success { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; padding: 12px 16px; border: 1px solid #cde7d8; border-radius: 14px; background: var(--emerald-tint); color: var(--emerald-deep); font-size: 13px; font-weight: 600; }
.auth-form { display: grid; gap: 10px; }
.auth-form label { margin-top: 8px; color: var(--ink); font-size: 13px; font-weight: 700; }
.auth-form label:first-child { margin-top: 0; }
.auth-input-wrap { position: relative; display: flex; align-items: center; border: 1px solid var(--line); border-radius: 12px; background: var(--paper); transition: border-color 0.2s, box-shadow 0.2s, background-color 0.2s; }
.auth-input-wrap > .v-icon { position: absolute; left: 15px; color: var(--emerald); pointer-events: none; }
.auth-input-wrap input { min-height: 50px; padding-left: 46px; border: 0; border-radius: 12px; background: transparent; box-shadow: none; }
.auth-input-wrap input:focus { box-shadow: none; }
.password-input-wrap input { padding-right: 52px; }
.code-input-wrap input { font-size: 22px; font-weight: 700; letter-spacing: 0.5em; }
.password-toggle { position: absolute; top: 50%; right: 8px; display: grid; place-items: center; width: 36px; height: 36px; transform: translateY(-50%); border: 0; border-radius: 10px; background: transparent; color: var(--ink-faint); cursor: pointer; transition: background-color 0.2s, color 0.2s; }
.password-toggle:hover { background: var(--mist); color: var(--emerald-deep); }
.password-eye-icon { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.auth-forgot-link { display: flex; justify-content: flex-end; }
.auth-forgot-link button { padding: 4px 2px; border: 0; background: none; color: var(--emerald-deep); cursor: pointer; font-size: 13px; font-weight: 700; }
.auth-forgot-link button:hover:not(:disabled) { text-decoration: underline; text-underline-offset: 4px; }
.auth-error { display: flex; align-items: flex-start; gap: 9px; padding: 12px 16px; border: 1px solid #f3cdc8; border-radius: 14px; background: var(--red-tint); color: var(--red); font-size: 13px; font-weight: 600; line-height: 1.6; }
.auth-note { display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; border-radius: 14px; background: var(--mist); color: var(--ink-soft); font-size: 13px; line-height: 1.65; }
.auth-note .v-icon { margin-top: 2px; color: var(--emerald); }
.auth-submit { width: 100%; min-height: 52px; margin-top: 8px; }
.auth-back { padding: 10px; border: 0; background: none; color: var(--ink-soft); cursor: pointer; font-size: 13.5px; font-weight: 600; }
.auth-back:hover { color: var(--emerald-deep); }
.auth-switch { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 4px 10px; margin-top: 24px; padding-top: 22px; border-top: 1px solid var(--line); color: var(--ink-soft); font-size: 13.5px; }
.auth-switch h2 { margin: 0; color: var(--ink-soft); font-family: var(--font-body); font-size: 13.5px; font-weight: 500; }
.auth-switch button { display: inline-flex; align-items: center; gap: 5px; padding: 4px 2px; border: 0; background: none; color: var(--emerald-deep); cursor: pointer; font-size: 13.5px; font-weight: 700; }
.auth-switch button:hover { text-decoration: underline; text-underline-offset: 4px; }
.signup-progress { display: flex; align-items: center; gap: 6px; margin-bottom: 22px; }
.signup-progress > span { flex: 1; height: 5px; border-radius: 99px; background: var(--line); transition: background-color 0.3s; }
.signup-progress > span.done { background: linear-gradient(90deg, var(--emerald), #2fb37a); }
.signup-progress small { margin-left: 8px; color: var(--ink-faint); font-size: 10.5px; font-weight: 800; letter-spacing: 0.12em; white-space: nowrap; }
.auth-secure { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 22px; color: var(--ink-faint); font-size: 12px; font-weight: 500; }
.auth-footer { display: grid; gap: 4px; max-width: 480px; color: var(--ink-faint); font-size: 12px; line-height: 1.6; text-align: center; }
.auth-powered-credit { font-weight: 600; color: var(--ink-soft); }

/* =====================================================================
   App shell: sidebar + top bar
   ===================================================================== */
.app-shell { display: grid; grid-template-columns: var(--sidebar-w) minmax(0, 1fr); min-height: 100vh; min-height: 100dvh; background: radial-gradient(ellipse 70% 40% at 100% 0%, rgb(23 132 101 / 6%), transparent 60%), var(--mist); }
.sidebar {
  position: sticky;
  top: 0;
  z-index: 60;
  display: flex;
  flex-direction: column;
  gap: 4px;
  height: 100vh;
  height: 100dvh;
  padding: 22px 16px 18px;
  overflow-x: hidden;
  overflow-y: auto;
  color: #fff;
  background: radial-gradient(ellipse 90% 40% at 0% 100%, rgb(94 190 150 / 16%), transparent 65%), linear-gradient(170deg, var(--pine-900), var(--pine-800) 55%, var(--pine-700));
  scrollbar-width: thin;
  scrollbar-color: rgb(255 255 255 / 20%) transparent;
}
.sidebar::after { position: absolute; top: -120px; right: -140px; width: 280px; height: 280px; content: ""; border: 1px solid rgb(255 255 255 / 8%); border-radius: 50%; box-shadow: 0 0 0 40px rgb(255 255 255 / 2%), 0 0 0 41px rgb(255 255 255 / 6%); pointer-events: none; }
.sidebar > * { position: relative; z-index: 1; }
.brand { display: flex; align-items: center; gap: 12px; padding: 4px 8px 22px; }
.brand-logo { flex: none; width: 46px; height: 46px; padding: 5px; border-radius: 14px; background: #fff; box-shadow: 0 0 0 4px rgb(255 255 255 / 8%), 0 10px 24px rgb(0 0 0 / 22%); object-fit: contain; }
.brand strong { display: block; font-family: var(--font-display); font-size: 16px; font-weight: 500; letter-spacing: -0.01em; line-height: 1.25; }
.brand small { display: block; margin-top: 2px; color: rgb(255 255 255 / 55%); font-size: 11.5px; font-weight: 600; }
.nav-label { margin: 10px 14px 6px; color: rgb(255 255 255 / 42%); font-size: 10.5px; font-weight: 800; letter-spacing: 0.14em; }
.nav-label.manage-label { margin-top: 20px; padding-top: 16px; border-top: 1px dashed rgb(255 255 255 / 14%); margin-inline: 0; padding-inline: 14px; }
.nav-item { position: relative; display: flex; align-items: center; gap: 12px; width: 100%; min-height: 46px; padding: 0 14px; border: 0; border-radius: 12px; background: transparent; color: rgb(255 255 255 / 70%); cursor: pointer; font-size: 14px; font-weight: 600; text-align: left; transition: background-color 0.2s, color 0.2s; }
.nav-item:hover { background: rgb(255 255 255 / 7%); color: #fff; }
.nav-item.active { background: rgb(255 255 255 / 11%); color: #fff; font-weight: 700; }
.nav-item.active::before { position: absolute; top: 11px; bottom: 11px; left: -16px; width: 4px; content: ""; border-radius: 0 4px 4px 0; background: var(--brass); }
.nav-item.active .v-icon { color: var(--brass-soft); }
.nav-dot { width: 8px; height: 8px; margin-left: auto; border-radius: 50%; background: var(--brass); box-shadow: 0 0 0 3px rgb(194 161 95 / 22%); }
.sidebar-bottom { display: grid; gap: 12px; margin-top: auto; padding-top: 22px; }
.help-card { padding: 16px; border: 1px solid rgb(255 255 255 / 10%); border-radius: 18px; background: rgb(255 255 255 / 6%); }
.help-icon { display: grid; place-items: center; width: 36px; height: 36px; margin-bottom: 12px; border-radius: 11px; background: rgb(194 161 95 / 18%); color: var(--brass-soft); }
.help-card b { display: block; font-size: 13.5px; }
.help-card p { margin: 6px 0 10px; color: rgb(255 255 255 / 60%); font-size: 12.5px; line-height: 1.6; }
.help-card button { display: inline-flex; align-items: center; gap: 6px; padding: 0; border: 0; background: none; color: var(--brass-soft); cursor: pointer; font-size: 12.5px; font-weight: 700; }
.user-row { display: flex; align-items: center; gap: 11px; padding: 10px 12px; border: 1px solid rgb(255 255 255 / 10%); border-radius: 16px; background: rgb(0 0 0 / 14%); }
.user-avatar, .tiny-avatar { display: grid; flex: none; place-items: center; border-radius: 50%; background: linear-gradient(145deg, #1f9a75, var(--pine-700)); color: #fff; font-size: 12.5px; font-weight: 800; letter-spacing: 0.02em; }
.user-avatar { width: 38px; height: 38px; box-shadow: 0 0 0 2px rgb(255 255 255 / 25%); }
.user-copy { flex: 1; min-width: 0; }
.user-copy b { display: block; overflow: hidden; font-size: 13px; text-overflow: ellipsis; white-space: nowrap; }
.user-copy small { color: rgb(255 255 255 / 52%); font-size: 11.5px; }
.user-row > .v-icon { color: rgb(255 255 255 / 45%); }

.main { display: flex; flex-direction: column; min-width: 0; min-height: 100vh; min-height: 100dvh; }
.topbar { position: sticky; top: 0; z-index: 40; display: flex; align-items: center; justify-content: space-between; gap: 16px; height: 72px; padding: 0 clamp(18px, 3vw, 40px); border-bottom: 1px solid var(--line); background: rgb(246 250 248 / 82%); backdrop-filter: saturate(1.4) blur(14px); }
.crumb { display: flex; align-items: center; gap: 7px; min-width: 0; color: var(--ink-faint); font-size: 13px; font-weight: 500; white-space: nowrap; }
.crumb .v-icon { color: #b4c3bb; }
.crumb b { overflow: hidden; color: var(--pine-800); font-weight: 700; text-overflow: ellipsis; }
.top-actions { display: flex; align-items: center; gap: 10px; }
.admin-signin-link { display: inline-flex; align-items: center; gap: 8px; min-height: 42px; padding: 8px 15px; border: 1px solid var(--line-strong); border-radius: 12px; background: #fff; color: var(--pine-800); cursor: pointer; font-size: 13px; font-weight: 700; transition: background-color 0.2s, border-color 0.2s, color 0.2s; }
.admin-signin-link:hover { border-color: #f1c6c1; background: var(--red-tint); color: var(--red); }
.signout-icon { width: 17px; height: 17px; }
.secure-pill { display: inline-flex; align-items: center; gap: 8px; padding: 7px 14px; border: 1px solid #cfe4d9; border-radius: 99px; background: var(--emerald-tint); color: var(--emerald-deep); font-size: 12px; font-weight: 700; white-space: nowrap; }
.icon-button { position: relative; display: grid; place-items: center; width: 42px; height: 42px; border: 1px solid var(--line); border-radius: 50%; background: #fff; color: var(--ink-soft); cursor: pointer; transition: background-color 0.2s, border-color 0.2s; }
.icon-button:hover { border-color: var(--line-strong); background: var(--mist); }
.icon-button i { position: absolute; top: 10px; right: 11px; width: 8px; height: 8px; border: 2px solid #fff; border-radius: 50%; background: var(--brass); }
.tiny-avatar { width: 38px; height: 38px; box-shadow: 0 0 0 2px #fff, 0 0 0 3px rgb(194 161 95 / 60%); }
.content { flex: 1; width: 100%; max-width: 1380px; margin: 0 auto; padding: clamp(20px, 3vw, 40px) clamp(16px, 3vw, 40px) 24px; }
.footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px 20px; padding: 16px clamp(18px, 3vw, 40px); border-top: 1px solid var(--line); background: rgb(255 255 255 / 55%); color: var(--ink-faint); font-size: 12px; }
.footer span { display: inline-flex; align-items: center; gap: 6px; }

/* ---------- Page heading ---------- */
.welcome-row { display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 18px 24px; margin-bottom: 26px; }
.eyebrow { display: inline-flex; align-items: center; flex-wrap: wrap; gap: 9px; margin-bottom: 10px; color: var(--emerald-deep); font-size: 11.5px; font-weight: 800; letter-spacing: 0.1em; }
.eyebrow h1 { display: inline; margin: 0; color: inherit; font: inherit; letter-spacing: inherit; }
.eyebrow-slash { color: var(--brass); }
.live-dot { width: 8px; height: 8px; border-radius: 50%; background: #2fb37a; box-shadow: 0 0 0 4px rgb(47 179 122 / 18%); }
.welcome-row h1 { margin: 0 0 8px; color: var(--pine-900); font-family: var(--font-display); font-size: clamp(28px, 3.3vw, 42px); font-weight: 500; letter-spacing: -0.025em; line-height: 1.1; }
.subhead { max-width: 580px; margin: 0; color: var(--ink-soft); font-size: 14.5px; line-height: 1.7; }

/* ---------- Overview ---------- */
.stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
.stat-card { padding: 22px; border: 1px solid var(--line); border-radius: 22px; background: #fff; box-shadow: var(--shadow-sm); transition: transform 0.25s, box-shadow 0.25s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
.stat-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; color: var(--ink-faint); font-size: 12.5px; font-weight: 700; }
.stat-icon { display: grid; flex: none; place-items: center; width: 42px; height: 42px; border-radius: 13px; }
.stat-icon.mint { background: var(--emerald-tint); color: var(--emerald-deep); }
.stat-icon.blue { background: var(--blue-tint); color: var(--blue); }
.stat-icon.amber { background: var(--amber-tint); color: var(--amber); }
.stat-icon.lilac { background: var(--lilac-tint); color: var(--lilac); }
.stat-value { margin: 18px 0 6px; color: var(--pine-900); font-family: var(--font-display); font-size: clamp(32px, 3vw, 44px); font-weight: 500; letter-spacing: -0.03em; line-height: 1; }
.stat-note { color: var(--ink-soft); font-size: 12.5px; line-height: 1.5; }
.stats-grid .stat-card:first-child { border-color: transparent; background: radial-gradient(ellipse 60% 100% at 100% 0%, rgb(255 255 255 / 12%), transparent 60%), linear-gradient(150deg, var(--pine-900), var(--pine-700)); box-shadow: var(--shadow-lg); }
.stats-grid .stat-card:first-child .stat-top { color: rgb(255 255 255 / 62%); }
.stats-grid .stat-card:first-child .stat-icon { background: rgb(194 161 95 / 20%); color: var(--brass-soft); box-shadow: inset 0 0 0 1px rgb(194 161 95 / 45%); }
.stats-grid .stat-card:first-child .stat-value { color: #fff; }
.stats-grid .stat-card:first-child .stat-note { color: rgb(255 255 255 / 62%); }

.dashboard-analytics { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 16px; margin-bottom: 28px; }
.analytics-card { padding: clamp(20px, 2.4vw, 30px); border: 1px solid var(--line); border-radius: 24px; background: #fff; box-shadow: var(--shadow-sm); }
.analytics-card-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 22px; }
.analytics-card-heading h2 { margin: 10px 0 4px; color: var(--pine-900); font-family: var(--font-display); font-size: 24px; font-weight: 500; letter-spacing: -0.02em; }
.analytics-card-heading p { margin: 0; color: var(--ink-soft); font-size: 13.5px; }
.analytics-period { display: inline-flex; align-items: center; gap: 7px; padding: 5px 12px; border-radius: 99px; background: var(--mist); color: var(--ink-soft); font-size: 12px; font-weight: 700; white-space: nowrap; }
.snapshot-icon { display: grid; place-items: center; width: 44px; height: 44px; border-radius: 14px; background: var(--emerald-tint); color: var(--emerald-deep); }
.readiness-content { display: flex; align-items: center; gap: clamp(20px, 3vw, 40px); }
.readiness-ring { position: relative; display: grid; flex: none; place-items: center; width: 176px; height: 176px; border-radius: 50%; }
.readiness-ring > div { display: grid; place-content: center; width: 138px; height: 138px; border-radius: 50%; background: #fff; box-shadow: inset 0 0 0 1px var(--line); text-align: center; }
.readiness-ring b { color: var(--pine-900); font-family: var(--font-display); font-size: 42px; font-weight: 500; letter-spacing: -0.03em; line-height: 1; }
.readiness-ring b small { margin-left: 2px; font-size: 20px; }
.readiness-ring span { margin-top: 4px; color: var(--ink-faint); font-size: 12px; font-weight: 600; }
.readiness-legend { display: grid; flex: 1; gap: 8px; min-width: 0; }
.readiness-legend > div { display: flex; align-items: center; gap: 12px; padding: 11px 14px; border: 1px solid var(--line); border-radius: 14px; background: var(--paper); }
.readiness-legend span { display: grid; flex: 1; min-width: 0; }
.readiness-legend b { color: var(--ink); font-size: 13.5px; }
.readiness-legend small { color: var(--ink-faint); font-size: 12px; }
.readiness-legend strong { color: var(--pine-900); font-size: 17px; font-weight: 800; }
.legend-dot { flex: none; width: 10px; height: 10px; border-radius: 50%; }
.complete-dot { background: #087e4b; }
.review-dot { background: #e7a846; }
.incomplete-dot { background: #d3d9d5; }
.analytics-footnote { display: flex; align-items: center; gap: 8px; margin-top: 20px; padding: 11px 14px; border-radius: 14px; background: var(--mist); color: var(--ink-soft); font-size: 12.5px; }
.snapshot-total { display: flex; align-items: baseline; flex-wrap: wrap; gap: 4px 12px; }
.snapshot-total strong { color: var(--pine-900); font-family: var(--font-display); font-size: 52px; font-weight: 500; letter-spacing: -0.03em; line-height: 1; }
.snapshot-total span { color: var(--ink-soft); font-size: 14px; font-weight: 600; }
.snapshot-track { height: 8px; margin-top: 18px; overflow: hidden; border-radius: 99px; background: var(--line); }
.snapshot-track i { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--emerald), #2fb37a); }
.snapshot-caption { display: flex; justify-content: space-between; margin-top: 8px; color: var(--ink-faint); font-size: 12.5px; font-weight: 600; }
.snapshot-caption b { color: var(--pine-900); }
.snapshot-divider { height: 1px; margin: 18px 0 6px; background: var(--line); }
.snapshot-detail { display: flex; align-items: center; gap: 14px; padding: 12px 0; border-bottom: 1px solid var(--line); }
.snapshot-detail:last-child { border-bottom: 0; }
.snapshot-detail > span:nth-child(2) { display: grid; flex: 1; gap: 2px; min-width: 0; }
.snapshot-detail b { color: var(--ink); font-size: 14px; }
.snapshot-detail small { color: var(--ink-faint); font-size: 12.5px; }
.snapshot-detail-icon { display: grid; flex: none; place-items: center; width: 42px; height: 42px; border-radius: 13px; }
.departments-icon { background: var(--lilac-tint); color: var(--lilac); }
.review-icon { background: var(--amber-tint); color: var(--amber); }
.snapshot-detail > button { display: grid; flex: none; place-items: center; width: 38px; height: 38px; border: 1px solid var(--line); border-radius: 50%; background: #fff; color: var(--pine-800); cursor: pointer; transition: background-color 0.2s, color 0.2s; }
.snapshot-detail > button:hover { border-color: var(--emerald-deep); background: var(--emerald-deep); color: #fff; }

.section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; margin: 0 0 14px; }
.section-heading h2 { margin: 0 0 4px; color: var(--pine-900); font-family: var(--font-display); font-size: 24px; font-weight: 500; letter-spacing: -0.02em; }
.section-heading p { margin: 0; color: var(--ink-soft); font-size: 14px; }

.directory-card { overflow: hidden; border: 1px solid var(--line); border-radius: 24px; background: #fff; box-shadow: var(--shadow-sm); }
.directory-toolbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px 20px; padding: 20px 24px; }
.table-title { display: flex; align-items: center; gap: 12px; }
.mini-icon { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 12px; background: var(--emerald-tint); color: var(--emerald-deep); }
.table-title b { display: block; color: var(--pine-900); font-size: 15px; }
.table-title small { display: block; color: var(--ink-faint); font-size: 12.5px; }
.table-tools { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
.search-box { display: flex; align-items: center; gap: 10px; min-width: 220px; height: 44px; padding: 0 16px; border: 1px solid var(--line); border-radius: 99px; background: #fff; color: var(--ink-faint); transition: border-color 0.2s, box-shadow 0.2s; }
.search-box:focus-within { border-color: var(--emerald); box-shadow: 0 0 0 4px var(--ring); }
.search-box input { flex: 1; min-width: 0; padding: 0; border: 0; outline: none; background: transparent; color: var(--ink); font-size: 13.5px; }
.filter-btn { min-height: 44px; border-radius: 99px; }
.more-btn { width: 44px; min-height: 44px; padding: 0; border-radius: 50%; }
.table-wrap { overflow-x: auto; border-top: 1px solid var(--line); -webkit-overflow-scrolling: touch; }
.table-wrap table { width: 100%; min-width: 720px; border-collapse: separate; border-spacing: 0; }
.table-wrap th { padding: 14px 22px; border-bottom: 1px solid var(--line); background: var(--paper); color: var(--ink-faint); font-size: 10.5px; font-weight: 800; letter-spacing: 0.1em; text-align: left; white-space: nowrap; }
.table-wrap td { padding: 15px 22px; border-bottom: 1px solid var(--line); color: var(--ink-soft); font-size: 13.5px; font-weight: 500; vertical-align: middle; }
.table-wrap tbody tr { transition: background-color 0.2s; }
.table-wrap tbody tr:hover { background: var(--paper); }
.table-wrap tbody tr:last-child td { border-bottom: 0; }
.table-wrap td b { color: var(--ink); font-size: 14px; font-weight: 700; }
.directory-card .table-wrap tbody tr { cursor: pointer; }
.person-cell { display: flex; align-items: center; gap: 12px; }
.person-avatar { display: grid; flex: none; place-items: center; width: 40px; height: 40px; border-radius: 50%; color: var(--emerald-deep); font-size: 12.5px; font-weight: 800; }
.person-cell b { display: block; }
.person-cell small { display: block; color: var(--ink-faint); font-size: 12px; }
.id-pill { display: inline-flex; padding: 4px 11px; border-radius: 99px; background: var(--mist); color: var(--pine-800); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; font-weight: 600; }
.status-pill { display: inline-flex; align-items: center; gap: 7px; padding: 5px 12px; border-radius: 99px; background: #eef1ef; color: #5d6f66; font-size: 12px; font-weight: 700; white-space: nowrap; }
.status-pill i { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.status-pill.complete { background: var(--emerald-tint); color: var(--emerald-deep); }
.status-pill.in-review { background: var(--amber-tint); color: var(--amber); }
.status-pill.incomplete { background: #eef1ef; color: #5d6f66; }
.row-more { display: grid; place-items: center; width: 36px; height: 36px; border: 0; border-radius: 10px; background: transparent; color: var(--ink-faint); cursor: pointer; }
.row-more:hover { background: var(--mist); color: var(--pine-800); }
.table-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; padding: 16px 24px; border-top: 1px solid var(--line); color: var(--ink-faint); font-size: 13px; }
.table-footer b { color: var(--ink-soft); }

.bottom-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 16px; margin-top: 18px; }
.completion-card { display: flex; align-items: center; gap: 18px; padding: clamp(20px, 2.4vw, 28px); border: 1px solid #ecdcb5; border-radius: 24px; background: linear-gradient(145deg, #fffaf0, #f7f1e2); }
.completion-icon { display: grid; flex: none; place-items: center; width: 52px; height: 52px; border-radius: 16px; background: #fff; box-shadow: 0 0 0 1px #e7dcc1; color: var(--amber); }
.completion-copy { flex: 1; min-width: 0; }
.completion-copy b { color: var(--pine-900); font-family: var(--font-display); font-size: 20px; font-weight: 500; }
.completion-copy p { margin: 4px 0 10px; color: var(--ink-soft); font-size: 13.5px; line-height: 1.65; }
.completion-copy button { display: inline-flex; align-items: center; gap: 6px; padding: 0; border: 0; background: none; color: var(--emerald-deep); cursor: pointer; font-size: 13px; font-weight: 700; }
.completion-copy button:hover { text-decoration: underline; text-underline-offset: 4px; }
.progress-ring { display: grid; flex: none; place-items: center; width: 64px; height: 64px; border-radius: 50%; background: #fff; box-shadow: 0 0 0 5px rgb(194 161 95 / 22%); color: var(--amber); font-family: var(--font-display); font-size: 24px; font-weight: 500; }
.activity-card { padding: clamp(18px, 2.2vw, 26px); border: 1px solid var(--line); border-radius: 24px; background: #fff; box-shadow: var(--shadow-sm); }
.activity-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; color: var(--ink-faint); }
.activity-head b { color: var(--pine-900); font-family: var(--font-display); font-size: 20px; font-weight: 500; }
.activity-card > button { display: flex; align-items: center; gap: 14px; width: 100%; margin-bottom: 10px; padding: 13px 14px; border: 1px solid var(--line); border-radius: 16px; background: #fff; color: var(--ink-faint); cursor: pointer; text-align: left; transition: border-color 0.2s, background-color 0.2s, transform 0.2s; }
.activity-card > button:last-child { margin-bottom: 0; }
.activity-card > button:hover { transform: translateX(2px); border-color: var(--line-strong); background: var(--paper); }
.activity-card > button > span:nth-child(2) { display: grid; flex: 1; gap: 2px; min-width: 0; }
.activity-card > button b { color: var(--ink); font-size: 14px; }
.activity-card > button small { color: var(--ink-soft); font-size: 12.5px; }
.quick-icon { display: grid; flex: none; place-items: center; width: 44px; height: 44px; border-radius: 13px; }
.qi-green { background: var(--emerald-tint); color: var(--emerald-deep); }
.qi-blue { background: var(--blue-tint); color: var(--blue); }

/* =====================================================================
   Profile setup
   ===================================================================== */
.profile-heading-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
.draft-pill { display: inline-flex; align-items: center; gap: 8px; padding: 8px 15px; border: 1px solid var(--line); border-radius: 99px; background: #fff; color: var(--ink-soft); font-size: 12.5px; font-weight: 700; }
.draft-pill i { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.draft-pill.status-complete { border-color: #cfe4d9; background: var(--emerald-tint); color: var(--emerald-deep); }
.draft-pill.status-in-review { border-color: #ecdcb5; background: var(--amber-tint); color: var(--amber); }
.profile-print-button { display: inline-flex; align-items: center; gap: 9px; min-height: 44px; padding: 9px 18px; border: 1px solid var(--line-strong); border-radius: 12px; background: #fff; color: var(--pine-800); cursor: pointer; font-size: 13px; font-weight: 700; transition: background-color 0.2s, border-color 0.2s; }
.profile-print-button:hover { border-color: var(--emerald); background: var(--emerald-tint); }
.profile-print-button svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

.profile-layout { display: grid; grid-template-columns: 300px minmax(0, 1fr); gap: 22px; align-items: start; }
.profile-side { position: sticky; top: 92px; display: grid; gap: 4px; padding: 14px; border: 1px solid var(--line); border-radius: 26px; background: #fff; box-shadow: var(--shadow-sm); }
.profile-person { display: grid; justify-items: center; gap: 2px; margin-bottom: 10px; padding: 22px 12px 20px; border-radius: 20px; text-align: center; color: #fff; background: radial-gradient(ellipse 70% 90% at 100% 0%, rgb(255 255 255 / 12%), transparent 60%), linear-gradient(150deg, var(--pine-900), var(--pine-700)); }
.profile-photo { display: grid; place-items: center; width: 68px; height: 68px; margin-bottom: 8px; border-radius: 50%; background: rgb(255 255 255 / 12%); box-shadow: 0 0 0 3px rgb(194 161 95 / 55%); color: #fff; font-family: var(--font-display); font-size: 24px; font-weight: 500; }
.profile-person b { max-width: 100%; overflow: hidden; font-size: 14.5px; text-overflow: ellipsis; white-space: nowrap; }
.profile-person small { color: rgb(255 255 255 / 60%); font-size: 12px; }
.step-item { display: flex; align-items: center; gap: 12px; width: 100%; min-height: 48px; padding: 0 12px; border: 0; border-radius: 14px; background: transparent; color: var(--ink-soft); cursor: pointer; font-size: 14px; font-weight: 600; text-align: left; transition: background-color 0.2s, color 0.2s; }
.step-item:hover { background: var(--mist); color: var(--pine-800); }
.step-item.selected { background: var(--emerald-tint); color: var(--emerald-deep); font-weight: 700; box-shadow: inset 0 0 0 1px #cfe4d9; }
.step-icon { display: grid; flex: none; place-items: center; width: 32px; height: 32px; border-radius: 10px; background: var(--mist); color: var(--ink-faint); }
.step-item.selected .step-icon { background: var(--emerald-deep); color: #fff; }
.step-item > span:nth-child(2) { flex: 1; }
.step-check { color: #2fb37a; }
.side-hint { display: flex; align-items: flex-start; gap: 8px; margin-top: 8px; padding: 12px 14px; border-radius: 14px; background: var(--mist); color: var(--ink-soft); font-size: 12.5px; line-height: 1.6; }
.side-hint .v-icon { margin-top: 2px; color: var(--emerald); }

.profile-form { min-width: 0; padding: clamp(20px, 3vw, 38px); border: 1px solid var(--line); border-radius: 28px; background: #fff; box-shadow: var(--shadow-sm); }
.form-head { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 14px 20px; margin-bottom: 28px; padding-bottom: 22px; border-bottom: 1px solid var(--line); }
.form-head h2 { margin: 10px 0 6px; color: var(--pine-900); font-family: var(--font-display); font-size: clamp(26px, 2.8vw, 34px); font-weight: 500; letter-spacing: -0.02em; }
.form-head p { max-width: 520px; margin: 0; color: var(--ink-soft); font-size: 14px; line-height: 1.7; }
.form-head-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
.clear-section-icon { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.required-note { color: var(--ink-faint); font-size: 12.5px; font-weight: 600; }
.required-note i, .fields-grid label i, .education-entry label i { color: var(--red); font-style: normal; }

.fields-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 22px 22px; }
.fields-grid > div { display: grid; gap: 9px; align-content: start; min-width: 0; }
.fields-grid label { color: var(--ink); font-size: 13px; font-weight: 700; }
.fields-grid .field-wide { grid-column: 1 / -1; }
.add-personnel-option { justify-self: start; padding: 2px 0; border: 0; background: none; color: var(--emerald-deep); cursor: pointer; font-size: 12.5px; font-weight: 700; }
.add-personnel-option:hover:not(:disabled) { text-decoration: underline; text-underline-offset: 3px; }
.add-personnel-option:disabled { opacity: 0.45; cursor: not-allowed; }
.inline-option-add { display: flex; gap: 8px; padding: 10px; border: 1px dashed var(--line-strong); border-radius: 14px; background: var(--paper); }
.inline-option-add input { min-height: 42px; background: #fff; }
.inline-option-add button { flex: none; min-height: 42px; padding: 8px 16px; border: 0; border-radius: 11px; background: var(--emerald-deep); color: #fff; cursor: pointer; font-size: 13px; font-weight: 700; }
.inline-option-add button:disabled { opacity: 0.5; cursor: not-allowed; }

.job-options { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr)); gap: 12px; }
.job-options > label { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border: 1px solid var(--line); border-radius: 16px; background: var(--paper); color: var(--ink-soft); cursor: pointer; font-size: 14px; font-weight: 600; transition: border-color 0.2s, background-color 0.2s, color 0.2s; }
.job-options > label:hover { border-color: var(--line-strong); }
.job-options > label:has(input:checked) { border-color: var(--emerald); background: var(--emerald-tint); color: var(--emerald-deep); box-shadow: 0 0 0 3px var(--ring); }
.job-options input { flex: none; width: 18px; height: 18px; margin: 0; accent-color: var(--emerald-deep); cursor: pointer; }
.job-options > .add-personnel-option, .job-options > .inline-option-add { grid-column: 1 / -1; }

.education-list { display: grid; gap: 18px; }
.education-entry { padding: clamp(16px, 2vw, 24px); border: 1px solid var(--line); border-radius: 22px; background: var(--paper); }
.education-entry-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
.education-entry-head b { display: block; color: var(--pine-900); font-family: var(--font-display); font-size: 18px; font-weight: 500; }
.education-entry-head small { display: block; color: var(--ink-faint); font-size: 12.5px; }
.remove-education { display: inline-flex; align-items: center; gap: 7px; padding: 7px 13px; border: 0; border-radius: 10px; background: var(--red-tint); color: var(--red); cursor: pointer; font-size: 12.5px; font-weight: 700; }
.remove-education:hover { filter: brightness(0.96); }
.interface-icon { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.education-entry .fields-grid input, .education-entry .fields-grid select { background-color: #fff; }
.education-note { display: flex; align-items: center; gap: 8px; margin: 0; color: var(--ink-faint); font-size: 12.5px; }

.olevel-panel { margin-top: 10px; padding: clamp(18px, 2.4vw, 28px); border: 1px solid #cfe4d9; border-radius: 24px; background: linear-gradient(145deg, #f3faf6, #e9f4ee); }
.olevel-panel-heading { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px 18px; margin-bottom: 20px; }
.olevel-panel-heading h3 { margin: 10px 0 6px; color: var(--pine-900); font-family: var(--font-display); font-size: 22px; font-weight: 500; letter-spacing: -0.015em; }
.olevel-panel-heading p { max-width: 560px; margin: 0; color: var(--ink-soft); font-size: 13.5px; line-height: 1.7; }
.olevel-limit-pill { padding: 6px 14px; border: 1px solid #cfe4d9; border-radius: 99px; background: #fff; color: var(--emerald-deep); font-size: 12px; font-weight: 700; }
.olevel-exam-card { margin-bottom: 14px; padding: clamp(16px, 2vw, 22px); border: 1px solid var(--line); border-radius: 20px; background: #fff; box-shadow: var(--shadow-sm); }
.olevel-exam-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
.olevel-exam-heading b { display: block; color: var(--pine-900); font-size: 15px; }
.olevel-exam-heading small { display: block; color: var(--ink-faint); font-size: 12.5px; }
.olevel-fields { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.olevel-subject-head { margin: 22px 0 12px; padding-top: 18px; border-top: 1px solid var(--line); }
.olevel-subject-head b { display: block; color: var(--ink); font-size: 14px; }
.olevel-subject-head small { color: var(--ink-faint); font-size: 12.5px; }
.olevel-subject-row { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr) 46px; align-items: end; gap: 12px; margin-bottom: 12px; padding: 14px; border: 1px solid var(--line); border-radius: 16px; background: var(--paper); }
.olevel-subject-row > div { display: grid; gap: 8px; min-width: 0; }
.olevel-subject-row label { color: var(--ink); font-size: 12.5px; font-weight: 700; }
.olevel-subject-row input, .olevel-subject-row select { background-color: #fff; }
.olevel-remove-subject { display: grid; place-items: center; width: 46px; height: 48px; border: 0; border-radius: 12px; background: var(--red-tint); color: var(--red); cursor: pointer; font-size: 22px; font-weight: 700; line-height: 1; }
.olevel-remove-subject:hover { filter: brightness(0.95); }
.olevel-rule-note { margin: 14px 2px 0; color: var(--ink-soft); font-size: 12.5px; line-height: 1.65; }

.form-footer { position: sticky; bottom: 14px; z-index: 5; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-top: 32px; padding: 14px 16px; border: 1px solid var(--line); border-radius: 20px; background: rgb(255 255 255 / 92%); backdrop-filter: blur(12px); box-shadow: 0 18px 40px -16px rgb(8 46 39 / 28%); }
.form-footer > div { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }

/* =====================================================================
   Administration
   ===================================================================== */
.admin-card { padding: clamp(20px, 3vw, 36px); border: 1px solid var(--line); border-radius: 28px; background: #fff; box-shadow: var(--shadow-sm); }
.admin-card-head { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 16px 24px; margin-bottom: 22px; }
.admin-card h2 { margin: 0 0 6px; color: var(--pine-900); font-family: var(--font-display); font-size: 24px; font-weight: 500; letter-spacing: -0.02em; }
.admin-card > p, .admin-card-head p { margin: 0; color: var(--ink-soft); font-size: 14px; line-height: 1.7; }
.login-card { max-width: 480px; margin: 0 auto; }
.dialog-symbol { display: grid; place-items: center; width: 52px; height: 52px; margin-bottom: 14px; border-radius: 16px; background: linear-gradient(145deg, #1f9a75, var(--pine-700)); box-shadow: 0 12px 24px -10px rgb(15 106 81 / 55%); color: #fff; }
.dialog-label { display: block; margin: 16px 0 8px; color: var(--ink-soft); font-size: 11px; font-weight: 800; letter-spacing: 0.1em; }
.dialog-input { display: block; }
.dialog-password-wrap { position: relative; }
.dialog-password-wrap .dialog-input { padding-right: 52px; }
.dialog-submit { width: 100%; min-height: 52px; margin-top: 22px; }
.dialog-tip { display: flex; align-items: flex-start; gap: 10px; margin-top: 18px; padding: 12px 16px; border-radius: 14px; background: var(--mist); color: var(--ink-soft); font-size: 13px; line-height: 1.65; }
.dialog-tip .v-icon { margin-top: 2px; color: var(--emerald); }

.lookup-filters { display: flex; align-items: flex-end; flex-wrap: wrap; gap: 12px; }
.lookup-category-filter { display: grid; gap: 6px; color: var(--ink-faint); font-size: 12px; font-weight: 700; }
.lookup-category-filter select { min-width: 200px; }
.lookup-import { display: flex; align-items: center; flex-wrap: wrap; gap: 12px 18px; margin-bottom: 22px; padding: 14px 18px; border: 1px dashed var(--line-strong); border-radius: 18px; background: var(--paper); }
.lookup-import > label { display: flex; align-items: center; gap: 12px; color: var(--emerald-deep); cursor: pointer; }
.lookup-import > label input { display: none; }
.lookup-import > label > span { display: grid; }
.lookup-import > label b { color: var(--ink); font-size: 14px; }
.lookup-import > label small { color: var(--ink-faint); font-size: 12.5px; }
.import-result { margin: 0; padding: 8px 14px; border-radius: 12px; background: var(--emerald-tint); color: var(--emerald-deep); font-size: 13px; font-weight: 600; line-height: 1.6; }
.options-grid { display: grid; gap: 12px; }
.option-group { border: 1px solid var(--line); border-radius: 20px; background: var(--paper); transition: border-color 0.2s, background-color 0.2s; }
.option-group.expanded { border-color: #cfe4d9; background: #fff; box-shadow: var(--shadow-sm); }
.option-title { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; }
.option-expand { display: flex; flex: 1; align-items: center; gap: 12px; min-width: 0; padding: 4px 0; border: 0; background: none; color: var(--emerald-deep); cursor: pointer; text-align: left; }
.option-expand > span { display: grid; }
.option-expand b { color: var(--ink); font-size: 14.5px; }
.option-expand small { color: var(--ink-faint); font-size: 12.5px; }
.add-option { min-height: 36px; padding: 6px 13px; font-size: 12.5px; }
.chips { display: flex; flex-wrap: wrap; gap: 8px; padding: 4px 18px 18px; }
.chips > span { display: inline-flex; align-items: center; gap: 8px; padding: 6px 6px 6px 14px; border: 1px solid var(--line); border-radius: 99px; background: var(--paper); color: var(--ink); font-size: 13px; font-weight: 600; }
.lookup-parent { padding: 2px 8px; border-radius: 99px; background: var(--mist); color: var(--ink-faint); font-size: 11px; font-weight: 700; }
.lookup-option-actions { display: inline-flex; gap: 2px; }
.lookup-option-actions button { display: grid; place-items: center; width: 28px; height: 28px; border: 0; border-radius: 50%; background: transparent; color: var(--ink-faint); cursor: pointer; transition: background-color 0.2s, color 0.2s; }
.lookup-option-actions button:first-child:hover { background: var(--emerald-tint); color: var(--emerald-deep); }
.lookup-option-actions button:last-child:hover { background: var(--red-tint); color: var(--red); }
.lookup-option-actions .interface-icon { width: 14px; height: 14px; }
.empty-options { padding: 36px 20px; border: 1px dashed var(--line-strong); border-radius: 18px; color: var(--ink-faint); text-align: center; }

.admin-directory { padding: 0; overflow: hidden; }
.directory-filters { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)) auto auto; align-items: end; gap: 14px; padding: clamp(18px, 2.4vw, 28px); }
.directory-filters label { display: grid; gap: 8px; color: var(--ink); font-size: 12.5px; font-weight: 700; }
.directory-filters .primary-btn, .directory-filters .clear-filters { min-height: 48px; }
.admin-directory-meta { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px 20px; padding: 0 clamp(18px, 2.4vw, 28px) 16px; color: var(--ink-faint); font-size: 12.5px; }
.admin-directory-meta span:first-child { color: var(--pine-900); font-weight: 800; }
.status-select { min-height: 40px; padding: 8px 36px 8px 12px; border-radius: 10px; font-size: 13px; font-weight: 600; }
.empty-row { padding: 48px 20px !important; color: var(--ink-faint) !important; text-align: center; }
.directory-pagination { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 16px clamp(18px, 2.4vw, 28px); border-top: 1px solid var(--line); color: var(--ink-faint); font-size: 13px; }
.directory-pagination > div { display: flex; gap: 10px; }

.invitation-management { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
.invitation-panel { display: flex; flex-direction: column; align-items: flex-start; padding: clamp(22px, 3vw, 36px); border: 1px solid var(--line); border-radius: 28px; background: #fff; box-shadow: var(--shadow-sm); }
.invitation-panel-icon { display: grid; place-items: center; width: 52px; height: 52px; margin-bottom: 16px; border-radius: 16px; background: var(--emerald-tint); color: var(--emerald-deep); }
.invitation-panel h2 { margin: 10px 0 8px; color: var(--pine-900); font-family: var(--font-display); font-size: 26px; font-weight: 500; letter-spacing: -0.02em; }
.invitation-panel > p { max-width: 520px; margin: 0 0 20px; color: var(--ink-soft); font-size: 14px; line-height: 1.75; }
.invitation-form { display: grid; gap: 10px; width: 100%; }
.invitation-form label { color: var(--ink); font-size: 13px; font-weight: 700; }
.invitation-form .primary-btn { margin-top: 6px; }
.invitation-feedback { margin-top: 14px; }
.upload-area { position: relative; display: grid; justify-items: center; gap: 6px; width: 100%; margin-bottom: 14px; padding: 32px 20px; border: 2px dashed var(--line-strong); border-radius: 22px; background: var(--paper); color: var(--emerald-deep); cursor: pointer; text-align: center; transition: border-color 0.2s, background-color 0.2s; }
.upload-area:hover { border-color: var(--emerald); background: var(--emerald-tint); }
.upload-area input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.upload-area b { color: var(--ink); font-size: 14.5px; word-break: break-all; }
.upload-area small { color: var(--ink-faint); font-size: 12.5px; }

.admin-account-card { display: grid; gap: 24px; }
.admin-create-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 20px; padding: clamp(16px, 2.4vw, 26px); border: 1px solid #cfe4d9; border-radius: 22px; background: linear-gradient(180deg, #f6fbf8, var(--paper)); }
.admin-create-form label { display: grid; gap: 8px; color: var(--ink-soft); font-size: 11px; font-weight: 800; letter-spacing: 0.1em; }
.admin-create-form > div, .admin-create-form > p, .admin-create-form > button { grid-column: 1 / -1; }
.admin-create-form > button { justify-self: start; }
.permission-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 250px), 1fr)); gap: 10px; margin-top: 8px; }
.permission-choice { display: flex !important; align-items: flex-start; gap: 12px !important; padding: 13px 15px; border: 1px solid var(--line); border-radius: 16px; background: #fff; color: var(--ink) !important; cursor: pointer; font-size: 13.5px !important; font-weight: 600 !important; letter-spacing: 0 !important; transition: border-color 0.2s, background-color 0.2s; }
.permission-choice:has(input:checked) { border-color: var(--emerald); background: var(--emerald-tint); }
.permission-choice input { flex: none; width: 18px; height: 18px; min-height: 0; margin: 2px 0 0; padding: 0; accent-color: var(--emerald-deep); cursor: pointer; }
.permission-choice span { display: grid; gap: 2px; }
.permission-choice b { color: var(--ink); font-size: 13.5px; }
.permission-choice small { color: var(--ink-faint); font-size: 12px; font-weight: 500; line-height: 1.5; }
.admin-list { display: grid; gap: 12px; }
.admin-list-row { display: grid; grid-template-columns: minmax(180px, 0.6fr) minmax(0, 1.4fr); gap: 18px; padding: 18px 20px; border: 1px solid var(--line); border-radius: 22px; background: var(--paper); }
.admin-list-row > div:first-child b { display: block; color: var(--pine-900); font-size: 15px; }
.admin-list-row > div:first-child small { color: var(--ink-faint); font-size: 12.5px; overflow-wrap: anywhere; }
.admin-access-editor { display: grid; gap: 12px; min-width: 0; }
.admin-access-editor .permission-list { margin-top: 0; }
.admin-access-editor .primary-btn { justify-self: start; }

.settings-card { padding: 8px 20px; }
.setting-row { display: flex; align-items: center; gap: 16px; padding: 18px 4px; border-bottom: 1px solid var(--line); color: var(--ink-faint); }
.setting-row:last-child { border-bottom: 0; }
.setting-row > div { flex: 1; }
.setting-row b { display: block; color: var(--ink); font-size: 14.5px; }
.setting-row small { color: var(--ink-faint); font-size: 12.5px; }
.setting-symbol { display: grid; place-items: center; width: 44px; height: 44px; border-radius: 13px; background: var(--emerald-tint); color: var(--emerald-deep); }

/* =====================================================================
   Messages
   ===================================================================== */
.chat-workspace { display: grid; grid-template-columns: 340px minmax(0, 1fr); gap: 18px; height: min(740px, calc(100dvh - 250px)); min-height: 540px; }
.chat-inbox, .chat-panel { display: flex; flex-direction: column; min-height: 0; border: 1px solid var(--line); border-radius: 26px; background: #fff; box-shadow: var(--shadow-sm); }
.chat-inbox { padding: 22px 18px 18px; }
.chat-inbox-heading { padding: 0 6px 16px; }
.chat-inbox-heading h2 { margin: 10px 0 4px; color: var(--pine-900); font-family: var(--font-display); font-size: 24px; font-weight: 500; letter-spacing: -0.02em; }
.chat-inbox-heading p { margin: 0; color: var(--ink-soft); font-size: 13px; }
.chat-search { display: flex; gap: 8px; margin-bottom: 14px; }
.chat-search input { flex: 1; min-width: 0; min-height: 44px; padding: 10px 14px; border: 1px solid var(--line); border-radius: 12px; outline: none; background: var(--paper); font-size: 13.5px; }
.chat-search input:focus { border-color: var(--emerald); box-shadow: 0 0 0 4px var(--ring); }
.chat-search .primary-btn { min-height: 44px; padding: 8px 16px; }
.chat-contact-list { display: grid; flex: 1; align-content: start; gap: 6px; min-height: 0; overflow-y: auto; }
.chat-contact { display: flex; align-items: center; gap: 12px; width: 100%; padding: 11px 12px; border: 1px solid transparent; border-radius: 16px; background: transparent; cursor: pointer; text-align: left; transition: background-color 0.2s, border-color 0.2s; }
.chat-contact:hover { background: var(--mist); }
.chat-contact.selected { border-color: #cfe4d9; background: var(--emerald-tint); }
.chat-avatar { display: grid; flex: none; place-items: center; width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(145deg, #1f9a75, var(--pine-700)); color: #fff; font-size: 12.5px; font-weight: 800; }
.personnel-service-avatar { border-radius: 14px; box-shadow: 0 0 0 3px rgb(194 161 95 / 40%); }
.chat-contact-copy { display: grid; flex: 1; min-width: 0; }
.chat-contact-copy b { overflow: hidden; color: var(--ink); font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }
.chat-contact-copy small { overflow: hidden; color: var(--ink-faint); font-size: 12.5px; text-overflow: ellipsis; white-space: nowrap; }
.chat-active-dot { width: 9px; height: 9px; border-radius: 50%; background: #2fb37a; box-shadow: 0 0 0 3px rgb(47 179 122 / 20%); }
.chat-empty { margin: 8px 6px; color: var(--ink-faint); font-size: 13px; line-height: 1.7; }
.personnel-chat-note { display: flex; gap: 12px; margin-top: 14px; padding: 16px; border: 1px solid #cfe4d9; border-radius: 18px; background: linear-gradient(145deg, #f3faf6, #e9f4ee); }
.personnel-note-icon { display: grid; flex: none; place-items: center; width: 38px; height: 38px; border-radius: 12px; background: #fff; color: var(--emerald-deep); }
.personnel-note-copy { display: grid; gap: 2px; min-width: 0; }
.personnel-note-kicker { color: var(--emerald-deep); font-size: 10.5px; font-weight: 800; letter-spacing: 0.12em; }
.personnel-note-copy b { color: var(--pine-900); font-size: 13.5px; }
.personnel-note-copy p { margin: 2px 0 0; color: var(--ink-soft); font-size: 12.5px; line-height: 1.6; }
.chat-panel { overflow: hidden; background: var(--paper); }
.chat-header { display: flex; align-items: center; gap: 14px; padding: 16px 22px; border-bottom: 1px solid var(--line); background: #fff; }
.chat-header > div { flex: 1; min-width: 0; }
.chat-header b { display: block; color: var(--pine-900); font-size: 15px; }
.chat-header small { display: block; overflow: hidden; color: var(--ink-faint); font-size: 12.5px; text-overflow: ellipsis; white-space: nowrap; }
.chat-live-label { display: inline-flex; align-items: center; gap: 8px; padding: 5px 12px; border-radius: 99px; background: var(--emerald-tint); color: var(--emerald-deep); font-size: 12px; font-weight: 700; white-space: nowrap; }
.chat-live-label i { width: 7px; height: 7px; border-radius: 50%; background: #2fb37a; box-shadow: 0 0 0 3px rgb(47 179 122 / 22%); }
.chat-messages { display: flex; flex: 1; flex-direction: column; gap: 12px; min-height: 0; padding: 22px; overflow-y: auto; background: radial-gradient(ellipse 80% 40% at 100% 0%, rgb(23 132 101 / 5%), transparent 60%), var(--paper); }
.chat-start, .chat-pick-person { display: grid; flex: 1; justify-items: center; align-content: center; gap: 6px; padding: 30px 20px; text-align: center; }
.chat-pick-person { height: 100%; }
.chat-start-icon { display: grid; place-items: center; width: 56px; height: 56px; margin-bottom: 8px; border-radius: 18px; background: var(--emerald-tint); color: var(--emerald-deep); }
.chat-start b, .chat-pick-person h2 { margin: 0; color: var(--pine-900); font-family: var(--font-display); font-size: 22px; font-weight: 500; }
.chat-start p, .chat-pick-person p { max-width: 340px; margin: 0; color: var(--ink-soft); font-size: 13.5px; line-height: 1.7; }
.chat-message { max-width: min(78%, 560px); padding: 11px 16px 9px; border: 1px solid var(--line); border-radius: 20px 20px 20px 6px; background: #fff; box-shadow: var(--shadow-sm); }
.chat-message small { display: block; margin-bottom: 3px; color: var(--emerald-deep); font-size: 11.5px; font-weight: 800; }
.chat-message p { margin: 0; color: var(--ink); font-size: 14px; line-height: 1.65; white-space: pre-wrap; overflow-wrap: anywhere; }
.chat-message time { display: block; margin-top: 5px; color: var(--ink-faint); font-size: 11px; font-weight: 600; }
.chat-message.mine { align-self: flex-end; border-color: transparent; border-radius: 20px 20px 6px 20px; background: linear-gradient(150deg, var(--pine-800), var(--pine-700)); box-shadow: 0 10px 22px -12px rgb(8 46 39 / 55%); }
.chat-message.mine small { color: var(--brass-soft); }
.chat-message.mine p { color: #fff; }
.chat-message.mine time { color: rgb(255 255 255 / 55%); text-align: right; }
.chat-error { margin: 0 22px 10px; padding: 10px 14px; border-radius: 12px; background: var(--red-tint); color: var(--red); font-size: 13px; font-weight: 600; }
.chat-composer { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: end; gap: 10px 12px; padding: 16px 20px 14px; border-top: 1px solid var(--line); background: #fff; }
.chat-composer textarea { min-height: 52px; max-height: 160px; padding: 13px 16px; border: 1px solid var(--line); border-radius: 16px; outline: none; background: var(--paper); color: var(--ink); font-size: 14px; line-height: 1.55; resize: none; transition: border-color 0.2s, box-shadow 0.2s; }
.chat-composer textarea:focus { border-color: var(--emerald); background: #fff; box-shadow: 0 0 0 4px var(--ring); }
.chat-composer .primary-btn { min-height: 52px; }
.chat-composer small { grid-column: 1 / -1; color: var(--ink-faint); font-size: 12px; }

/* =====================================================================
   Modals (teleported to <body>)
   ===================================================================== */
.pis-modal-backdrop { position: fixed; inset: 0; z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 20px; overflow-y: auto; background: rgb(6 31 26 / 60%); backdrop-filter: blur(6px); }
.dialog-card.pis-modal { position: relative; width: min(500px, 100%); max-height: calc(100dvh - 40px); padding: clamp(22px, 3vw, 34px); overflow-y: auto; border-radius: 28px; background: #fff; box-shadow: var(--shadow-lg); animation: pis-sheet 0.35s cubic-bezier(0.2, 0.7, 0.2, 1) both; }
.dialog-card.pis-modal::before { position: absolute; top: 0; left: 32px; right: 32px; height: 2px; content: ""; background: linear-gradient(90deg, transparent, var(--brass), transparent); }
@keyframes pis-sheet { from { opacity: 0; transform: translateY(16px) scale(0.985); } to { opacity: 1; transform: none; } }
@keyframes pis-sheet-up { from { opacity: 0.6; transform: translateY(40px); } to { opacity: 1; transform: none; } }
.dialog-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.dialog-close { display: grid; place-items: center; width: 38px; height: 38px; border: 1px solid var(--line); border-radius: 50%; background: #fff; color: var(--ink-soft); cursor: pointer; font-size: 22px; line-height: 1; transition: background-color 0.2s, color 0.2s; }
.dialog-close:hover { background: var(--red-tint); color: var(--red); }
.pis-modal h2 { margin: 8px 0 8px; color: var(--pine-900); font-family: var(--font-display); font-size: 27px; font-weight: 500; letter-spacing: -0.02em; line-height: 1.15; }
.pis-modal > p { margin: 0 0 6px; color: var(--ink-soft); font-size: 14px; line-height: 1.7; }
.pis-modal .form-kicker { margin-top: 4px; }
.otp-input { font-size: 24px; font-weight: 700; letter-spacing: 0.5em; text-align: center; }
.signup-link { display: block; width: 100%; margin-top: 14px; padding: 10px; border: 0; background: none; color: var(--ink-soft); cursor: pointer; font-size: 13px; font-weight: 600; text-align: center; }
.signup-link b { color: var(--emerald-deep); }
.signup-link:hover b { text-decoration: underline; text-underline-offset: 4px; }

/* =====================================================================
   SweetAlert2 (custom classes passed from the script)
   ===================================================================== */
.swal2-container { font-family: var(--font-body); }
.swal2-container.swal2-backdrop-show, .swal2-container.swal2-noanimation { background: rgb(6 31 26 / 60%) !important; backdrop-filter: blur(5px); }
.pis-swal-popup { width: min(460px, 92vw) !important; padding: 30px 28px 26px !important; border-radius: 28px !important; background: #fff; box-shadow: var(--shadow-lg) !important; }
.pis-swal-title { padding: 6px 0 0 !important; color: var(--pine-900) !important; font-family: var(--font-display) !important; font-size: 26px !important; font-weight: 500 !important; letter-spacing: -0.02em; line-height: 1.2 !important; }
.pis-swal-copy { margin: 10px 0 0 !important; color: var(--ink-soft) !important; font-size: 14px !important; line-height: 1.7 !important; }
.pis-swal-popup .swal2-icon { margin: 4px auto 6px; transform: scale(0.9); }
.pis-swal-input { min-height: 50px !important; margin: 18px 0 0 !important; border: 1px solid var(--line) !important; border-radius: 12px !important; background: var(--paper) !important; box-shadow: none !important; color: var(--ink) !important; font-size: 14.5px !important; }
.pis-swal-input:focus { border-color: var(--emerald) !important; box-shadow: 0 0 0 4px var(--ring) !important; }
.pis-swal-popup .swal2-input-label { margin: 16px 0 0; color: var(--ink-soft); font-size: 12.5px; font-weight: 700; }
.pis-swal-popup .swal2-validation-message { margin: 14px 0 0; border-radius: 12px; background: var(--red-tint); color: var(--red); font-size: 13px; font-weight: 600; }
.pis-swal-actions { gap: 10px !important; margin-top: 24px !important; }
.pis-swal-confirm, .pis-swal-cancel, .pis-swal-deny { min-height: 46px; padding: 11px 24px; border: 0; border-radius: 12px; cursor: pointer; font-family: var(--font-body); font-size: 14px; font-weight: 700; transition: transform 0.2s, filter 0.2s, background-color 0.2s; }
.pis-swal-confirm { background: linear-gradient(180deg, #1a8e6d, var(--emerald-deep)) !important; box-shadow: inset 0 1px 0 rgb(255 255 255 / 20%), 0 12px 24px -10px rgb(15 106 81 / 55%); color: #fff !important; }
.pis-swal-confirm:hover { transform: translateY(-1px); filter: brightness(1.06); }
.pis-swal-cancel { border: 1px solid var(--line-strong); background: #fff !important; color: var(--pine-800) !important; }
.pis-swal-cancel:hover { background: var(--emerald-tint) !important; border-color: var(--emerald); }
.pis-swal-deny { background: var(--red) !important; color: #fff !important; }
.pis-swal-popup button:focus-visible { outline: 3px solid var(--ring); outline-offset: 2px; box-shadow: none; }
.pis-swal-toast { padding: 12px 18px !important; border: 1px solid var(--line); border-radius: 18px !important; background: #fff !important; box-shadow: var(--shadow-lg) !important; }
.pis-swal-toast-title { margin: 0 0 0 6px !important; color: var(--ink) !important; font-family: var(--font-body) !important; font-size: 13.5px !important; font-weight: 600 !important; line-height: 1.5 !important; }
.pis-swal-toast .swal2-timer-progress-bar { background: var(--emerald); }

/* =====================================================================
   Printable personnel record
   ===================================================================== */
.personnel-print-document { display: none; }

@media print {
  @page { size: A4; margin: 14mm; }
  html, body { background: #fff !important; }
  body * { visibility: hidden !important; }
  .personnel-print-document, .personnel-print-document * { visibility: visible !important; }
  .app-shell { display: block !important; background: #fff !important; }
  .personnel-print-document {
    display: block !important;
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    color: #12261f;
    font-family: var(--font-body);
    font-size: 11px;
    line-height: 1.5;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .print-document-header { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 16px; padding-bottom: 14px; border-bottom: 3px double #115446; }
  .print-uch-mark { width: 64px; height: 64px; background: url("/images/uch-logo.png") center / contain no-repeat; }
  .print-document-header > div:nth-child(2) span { color: #7d8f86; font-size: 8.5px; font-weight: 800; letter-spacing: 0.14em; }
  .print-document-header h1 { margin: 4px 0 2px; color: #082e27; font-family: var(--font-display); font-size: 22px; font-weight: 500; }
  .print-document-header p { margin: 0; color: #4b5f57; font-size: 10px; }
  .print-record-stamp { padding: 8px 14px; border: 1.5px solid #115446; border-radius: 8px; text-align: center; }
  .print-record-stamp b { display: block; color: #115446; font-size: 10px; letter-spacing: 0.14em; }
  .print-record-stamp small { color: #7d8f86; font-size: 7.5px; letter-spacing: 0.12em; }
  .print-person-summary { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin: 16px 0; padding: 14px 16px; border-left: 4px solid #c2a15f; border-radius: 6px; background: #eff6f0; }
  .print-person-summary > div:first-child span { color: #0f6a51; font-size: 8px; font-weight: 800; letter-spacing: 0.14em; }
  .print-person-summary h2 { margin: 3px 0; color: #082e27; font-family: var(--font-display); font-size: 19px; font-weight: 500; }
  .print-person-summary p { margin: 0; color: #4b5f57; font-size: 10.5px; }
  .print-record-meta { display: grid; gap: 4px; text-align: right; }
  .print-record-meta span { color: #7d8f86; font-size: 8px; font-weight: 800; letter-spacing: 0.1em; }
  .print-record-meta b { display: block; color: #082e27; font-size: 12px; }
  .print-data-section { margin: 14px 0; break-inside: avoid; }
  .print-data-section h3 { display: flex; align-items: center; gap: 10px; margin: 0 0 8px; color: #115446; font-family: var(--font-display); font-size: 13px; font-weight: 500; }
  .print-data-section h3 i { flex: 1; height: 1px; background: #cfe0d5; }
  .print-data-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 10px; }
  .print-data-row { padding: 6px 9px; border: 1px solid #e0e9e3; border-radius: 4px; background: #fafcfb; break-inside: avoid; }
  .print-data-row span { display: block; color: #7d8f86; font-size: 7.5px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
  .print-data-row b { display: block; color: #12261f; font-size: 10.5px; font-weight: 700; overflow-wrap: anywhere; }
  .print-document-footer { display: flex; justify-content: space-between; gap: 10px; margin-top: 18px; padding-top: 8px; border-top: 1px solid #cfe0d5; color: #7d8f86; font-size: 8px; }
}

/* =====================================================================
   Responsive
   ===================================================================== */
@media (max-width: 1280px) {
  .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .directory-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .directory-filters .primary-btn, .directory-filters .clear-filters { grid-column: auto; }
}
@media (max-width: 1100px) {
  .dashboard-analytics, .bottom-grid, .invitation-management { grid-template-columns: minmax(0, 1fr); }
  .admin-list-row { grid-template-columns: minmax(0, 1fr); }
  .chat-workspace { grid-template-columns: 300px minmax(0, 1fr); }
}

/* Sidebar becomes a sticky horizontal navigation strip (no menu button is needed) */
@media (max-width: 960px) {
  .app-shell { display: block; }
  .sidebar { position: sticky; top: 0; flex-direction: row; align-items: center; gap: 6px; height: auto; padding: 10px 12px; overflow-x: auto; overflow-y: hidden; box-shadow: 0 10px 30px -12px rgb(8 46 39 / 55%); -webkit-overflow-scrolling: touch; }
  .sidebar::after { display: none; }
  .brand { flex: none; gap: 10px; padding: 0 10px 0 2px; }
  .brand-logo { width: 38px; height: 38px; }
  .brand strong { max-width: 130px; overflow: hidden; font-size: 13px; text-overflow: ellipsis; white-space: nowrap; }
  .brand small, .nav-label, .sidebar-bottom { display: none; }
  .nav-item { flex: none; width: auto; min-height: 42px; padding: 0 16px; border-radius: 99px; font-size: 13px; white-space: nowrap; }
  .nav-item.active::before { display: none; }
  .nav-item.active { background: rgb(255 255 255 / 16%); box-shadow: inset 0 0 0 1px rgb(194 161 95 / 55%); }
  .topbar { position: relative; height: 64px; }
  .secure-pill { display: none; }
  .profile-layout { grid-template-columns: minmax(0, 1fr); }
  .profile-side { position: sticky; top: 63px; z-index: 30; display: flex; gap: 6px; padding: 8px; overflow-x: auto; border-radius: 20px; scrollbar-width: none; }
  .profile-side::-webkit-scrollbar { display: none; }
  .profile-person, .side-hint { display: none; }
  .step-item { flex: none; width: auto; min-height: 44px; padding: 0 14px 0 8px; white-space: nowrap; }
  .step-item > span:nth-child(2) { flex: none; }
  .olevel-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 860px) {
  /* Sign-in: story panel collapses, mobile brand appears */
  .auth-frame { grid-template-columns: minmax(0, 1fr); }
  .auth-story { display: none; }
  .auth-main { justify-content: flex-start; padding: 28px 16px 40px; }
  .auth-mobile-brand { display: flex; }
  .chat-workspace { grid-template-columns: minmax(0, 1fr); height: auto; min-height: 0; }
  .chat-inbox { max-height: 300px; }
  .chat-panel { height: min(640px, 75dvh); }
  .personnel-chat-inbox .personnel-chat-note { display: none; }
  .personnel-chat-inbox { max-height: none; }
}

@media (max-width: 720px) {
  .content { padding: 20px 12px 20px; }
  .welcome-row { align-items: stretch; margin-bottom: 20px; }
  .welcome-row > .primary-btn { width: 100%; }
  .profile-heading-actions { width: 100%; }
  .profile-print-button { flex: 1; justify-content: center; }
  .topbar { padding: 0 14px; }
  .crumb span, .crumb .v-icon { display: none; }
  .admin-signin-link span { display: none; }
  .admin-signin-link { width: 42px; padding: 0; justify-content: center; border-radius: 50%; }
  .stats-grid { gap: 10px; }
  .stat-card { padding: 16px; border-radius: 20px; }
  .stat-value { margin-top: 14px; }
  .analytics-card, .directory-card, .completion-card, .activity-card { border-radius: 20px; }
  .readiness-content { flex-direction: column; align-items: stretch; }
  .readiness-ring { align-self: center; }
  .directory-toolbar { padding: 16px; }
  .table-tools { width: 100%; }
  .search-box { flex: 1; min-width: 0; }
  .table-footer { padding: 14px 16px; }
  .completion-card { flex-wrap: wrap; }
  .profile-form { border-radius: 22px; }
  .form-head { margin-bottom: 22px; }
  .fields-grid { grid-template-columns: minmax(0, 1fr); gap: 18px; }
  .fields-grid .field-wide { grid-column: auto; }
  .olevel-fields { grid-template-columns: minmax(0, 1fr); }
  .olevel-subject-row { grid-template-columns: minmax(0, 1fr) 46px; }
  .olevel-subject-row > div:first-child { grid-column: 1 / -1; }
  .olevel-subject-row > div:nth-child(2) { grid-column: 1; }
  .form-footer { bottom: 8px; padding: 10px; border-radius: 18px; }
  .form-footer > div { width: 100%; }
  .form-footer > div > button, .form-footer > .back-btn { flex: 1; }
  .form-footer > .back-btn { width: 100%; }
  .inline-option-add { flex-direction: column; }
  .admin-card, .invitation-panel { border-radius: 22px; }
  .directory-filters { grid-template-columns: minmax(0, 1fr); }
  .admin-create-form { grid-template-columns: minmax(0, 1fr); }
  .admin-create-form > button { justify-self: stretch; }
  .admin-access-editor .primary-btn { justify-self: stretch; }
  .lookup-filters { width: 100%; }
  .lookup-category-filter, .lookup-category-filter select { width: 100%; }
  .chat-message { max-width: 88%; }
  .chat-composer { grid-template-columns: minmax(0, 1fr); }
  .chat-composer .primary-btn { width: 100%; }
  .auth-card { border-radius: 24px; }
  .auth-card::before { left: 24px; right: 24px; }
  .signup-progress small { display: none; }

  /* Modals -> bottom sheets */
  .pis-modal-backdrop { align-items: flex-end; padding: 0; }
  .dialog-card.pis-modal { width: 100%; max-height: 94dvh; border-radius: 26px 26px 0 0; animation-name: pis-sheet-up; }

  /* 16px prevents iOS zoom on focus */
  .auth-input-wrap input, .fields-grid input, .fields-grid select, .fields-grid textarea, .dialog-input,
  .invitation-form input, .admin-create-form input, .directory-filters input, .lookup-category-filter select,
  .inline-option-add input, .olevel-subject-row input, .olevel-subject-row select, .chat-search input,
  .chat-composer textarea, .search-box input { font-size: 16px; }
}
@media (max-width: 420px) {
  .stats-grid { grid-template-columns: minmax(0, 1fr); }
}

@media (prefers-reduced-motion: reduce) {
  .auth-card, .dialog-card.pis-modal { animation: none; }
  .stat-card, .primary-btn, .auth-submit, .dialog-submit, .activity-card > button { transition: none; }
}
</style>