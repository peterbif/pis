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
  ).some(
    (category) => String(category).trim().toLowerCase().startsWith("junior staff")
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
const resetPasswordsMatch = computed(() => resetPassword.value.length > 0 && resetPassword.value === resetPasswordConfirm.value);
const resetPasswordValid = computed(() => /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,200}$/.test(resetPassword.value));
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
    fields: [
      "Institution",
      "Course",
      "Qualification",
      "Year of graduation",
    ],
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
  toggle.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.2 12s3.6-7 9.8-7 9.8 7 9.8 7-3.6 7-9.8 7-9.8-7-9.8-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
  toggle.addEventListener("click", () => {
    const reveal = input.type === "password";
    input.type = reveal ? "text" : "password";
    toggle.setAttribute("aria-label", reveal ? "Hide password" : "Show password");
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
      .filter((exam) =>
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
      ?.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" });
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
      notify(
        `${juniorOlevelMessage} Your entries have been saved.`,
        "warning"
      );
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
  if (!resetEmail.value.trim()) { resetError.value = "Enter your email address."; return; }
  authBusy.value = true;
  try {
    await store.csrf();
    await axios.post("/api/password/forgot", { email: resetEmail.value.trim().toLowerCase() });
    resetEmail.value = resetEmail.value.trim().toLowerCase();
    resetStep.value = "code";
    resetNotice.value = "If an account exists for this email, a six-digit code has been sent. The code expires in 10 minutes.";
  } catch (e) {
    resetError.value = humanError(e, "We could not send a reset code. Please try again.");
  } finally { authBusy.value = false; }
}
function continuePasswordReset() {
  resetError.value = "";
  if (!/^\d{6}$/.test(resetCode.value)) { resetError.value = "Enter the six-digit code from your email."; return; }
  resetStep.value = "password";
}
async function submitPasswordReset() {
  resetError.value = "";
  if (!resetPasswordValid.value) { resetError.value = "Use 8–200 characters with uppercase and lowercase letters, a number, and a symbol."; return; }
  if (!resetPasswordsMatch.value) { resetError.value = "The passwords do not match."; return; }
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
    authSuccess.value = response.data.message || "Your password has been changed. Sign in using your new password.";
    resetNotice.value = "Your password has been reset successfully. You can now sign in with your new password.";
    resetStep.value = "complete";
    resetPassword.value = "";
    resetPasswordConfirm.value = "";
  } catch (e) {
    resetError.value = humanError(e, "We could not reset your password. Please request a new code and try again.");
  } finally { authBusy.value = false; }
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
          <img :src="logoAsset" alt="University College Hospital, Ibadan" />
          <div>
            <b>University College Hospital</b><small>IBADAN</small>
          </div>
        </div>
        <div class="story-orbit orbit-one"></div>
        <div class="story-orbit orbit-two"></div>
        <div class="story-content">
    
          <h1>PERSONNEL INFORMATION SYSTEM</h1>
          <p>
            Your work matters to us as well as your records.
          </p>
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
          <section v-if="authView === 'forgot-password'" class="auth-reset-page" aria-labelledby="reset-page-title">
            <div class="auth-heading">
              <span class="auth-overline">ACCOUNT RECOVERY</span>
              <h2 id="reset-page-title">{{ resetStep === 'complete' ? 'Password updated' : 'Reset your password' }}</h2>
              <p v-if="resetStep === 'email'">Enter the email address linked to your UCH personnel account.</p>
              <p v-else-if="resetStep === 'code'">Enter the six-digit code sent to {{ resetEmail }}.</p>
              <p v-else-if="resetStep === 'password'">Choose a strong password for your account.</p>
              <p v-else>Your password has been changed successfully.</p>
            </div>
            <div v-if="resetNotice" class="auth-success" role="status"><v-icon icon="mdi-check-circle-outline" size="18" />{{ resetNotice }}</div>
            <form v-if="resetStep === 'email'" class="auth-form" @submit.prevent="requestPasswordResetCode">
              <label for="reset-email">Email address</label>
              <div class="auth-input-wrap"><v-icon icon="mdi-email-outline" size="18" /><input id="reset-email" v-model="resetEmail" type="email" autocomplete="email" required placeholder="you@example.com" /></div>
              <div v-if="resetError" class="auth-error" role="alert"><v-icon icon="mdi-alert-circle-outline" size="16" />{{ resetError }}</div>
              <button class="auth-submit" type="submit" :disabled="authBusy"><span>{{ authBusy ? 'Sending code…' : 'Send reset code' }}</span><v-icon icon="mdi-arrow-right" size="18" /></button>
            </form>
            <form v-else-if="resetStep === 'code'" class="auth-form" @submit.prevent="continuePasswordReset">
              <label for="reset-code">Six-digit verification code</label>
              <div class="auth-input-wrap"><v-icon icon="mdi-numeric" size="18" /><input id="reset-code" v-model="resetCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required placeholder="000000" /></div>
              <div class="auth-note"><v-icon icon="mdi-clock-outline" size="16" /><span>Check your inbox. Your code expires in 10 minutes.</span></div>
              <div v-if="resetError" class="auth-error" role="alert"><v-icon icon="mdi-alert-circle-outline" size="16" />{{ resetError }}</div>
              <button class="auth-submit" type="submit"><span>Continue</span><v-icon icon="mdi-arrow-right" size="18" /></button>
              <button class="auth-back" type="button" @click="resetStep = 'email'; resetError = ''">Change email address</button>
            </form>
            <form v-else-if="resetStep === 'password'" class="auth-form" @submit.prevent="submitPasswordReset">
              <label for="reset-new-password">New password</label>
              <div class="auth-input-wrap password-input-wrap"><v-icon icon="mdi-lock-outline" size="18" /><input id="reset-new-password" v-model="resetPassword" :type="showResetPassword ? 'text' : 'password'" autocomplete="new-password" required placeholder="Enter a new password" /><button class="password-toggle" type="button" :aria-label="showResetPassword ? 'Hide password' : 'Show password'" :aria-pressed="showResetPassword" @click="showResetPassword = !showResetPassword"><svg v-if="!showResetPassword" class="reset-eye-svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.2 12s3.5-6.5 9.8-6.5 9.8 6.5 9.8 6.5-3.5 6.5-9.8 6.5S2.2 12 2.2 12Z"/><circle cx="12" cy="12" r="3"/></svg><svg v-else class="reset-eye-svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 3l18 18M10.6 5.7A10.8 10.8 0 0 1 12 5.5c6.3 0 9.8 6.5 9.8 6.5a16.7 16.7 0 0 1-3.1 3.8M6.2 6.2C3.6 8 2.2 12 2.2 12s3.5 6.5 9.8 6.5c1.2 0 2.3-.2 3.3-.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg></button></div>
              <p class="auth-note">Use 8–200 characters with uppercase and lowercase letters, a number, and a symbol.</p>
              <label for="reset-confirm-password">Confirm new password</label>
              <div class="auth-input-wrap password-input-wrap"><v-icon icon="mdi-lock-check-outline" size="18" /><input id="reset-confirm-password" v-model="resetPasswordConfirm" :type="showResetPasswordConfirm ? 'text' : 'password'" autocomplete="new-password" required placeholder="Re-enter your new password" /><button class="password-toggle" type="button" :aria-label="showResetPasswordConfirm ? 'Hide password confirmation' : 'Show password confirmation'" :aria-pressed="showResetPasswordConfirm" @click="showResetPasswordConfirm = !showResetPasswordConfirm"><svg v-if="!showResetPasswordConfirm" class="reset-eye-svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.2 12s3.5-6.5 9.8-6.5 9.8 6.5 9.8 6.5-3.5 6.5-9.8 6.5S2.2 12 2.2 12Z"/><circle cx="12" cy="12" r="3"/></svg><svg v-else class="reset-eye-svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 3l18 18M10.6 5.7A10.8 10.8 0 0 1 12 5.5c6.3 0 9.8 6.5 9.8 6.5a16.7 16.7 0 0 1-3.1 3.8M6.2 6.2C3.6 8 2.2 12 2.2 12s3.5 6.5 9.8 6.5c1.2 0 2.3-.2 3.3-.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg></button></div>
              <p v-if="resetPasswordConfirm" class="reset-match-status" :class="resetPasswordsMatch ? 'is-match' : 'is-mismatch'" aria-live="polite">{{ resetPasswordsMatch ? 'Passwords match.' : 'Passwords do not match.' }}</p>
              <div v-if="resetError" class="auth-error" role="alert"><v-icon icon="mdi-alert-circle-outline" size="16" />{{ resetError }}</div>
              <button class="auth-submit" type="submit" :disabled="authBusy || !resetPasswordValid || !resetPasswordsMatch"><span>{{ authBusy ? 'Updating password…' : 'Reset password' }}</span><v-icon icon="mdi-check" size="18" /></button>
            </form>
            <div v-else-if="resetStep === 'complete'" class="auth-form">
              <button class="auth-submit" type="button" @click="authView = 'signin'">
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
                <button type="button" :disabled="authBusy" @click="openForgotPassword">Forgot password?</button>
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
                  >Your email is checked against email attached to payslip before
                  you can continue.</span
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
            >Having trouble getting in? Contact your PIS
            administrators @ Smart Classroom 2.</span
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
          <strong>Personnel Information System</strong
          ><small>UCH, IB</small>
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
            <span style="color:crimson">Sign out</span>
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
                  Select & fill the details that apply to you. You can save and come
                  back at any time.
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
                    <path d="M4 7h16M9 7V4h6v3m-9 0 1 13h10l1-13M10 11v5m4-5v5" />
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
                      Optional for Senior Staff. You may save complete or partial
                      O’Level details without making them a submission requirement.
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
                    f.includes('address') ||
                    f === 'Permanent address',
                }"
              >
                <label
                  >{{ f }}
                  <i
                    v-if="j < 2 || f === 'Date of first appointment'"
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
                  {{ profileTab < sections.length - 1 ? "Save & continue" : "Submit profile" }}
                  <v-icon
                    :icon="profileTab < sections.length - 1 ? 'mdi-arrow-right' : 'mdi-check'"
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
             <h1> PERSONNEL INFORMATION SYSTEM</h1>
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
                  <v-icon icon="mdi-headset" size="21" />
                </span
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
                <p>Only you and authorised Personnel Services administrators can read these messages.</p>
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
                    Send a message to the ITD Team. Your enquiry
                    and their reply will stay here.
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
        <span>© 2026 University College Hospital · Personnel Information System</span
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