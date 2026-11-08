const EXTENSIONS = ['Jr.', 'Sr.', 'Other'];
const OTHER_EXTENSIONS = ['I', 'II', 'III', 'IV', 'IX', 'X'];
const COURSES = ['BSIT', 'BSCS', 'BSCpE', 'BSECE', 'BSEd'];
const YEAR_LEVELS = ['1st', '2nd', '3rd', '4th'];

const hasEdgeSpace = (v) => v !== v.trim();
const isTitleCase = (v) => /^[A-Z][a-z]*( [A-Z][a-z]*)*$/.test(v);

function validateIdNumber(v) {
    if (v === '') return 'ID Number is required.';
    if (!/^\d{4}-\d{4}$/.test(v)) return 'ID Number must follow the format xxxx-xxxx.';
    return '';
}

function validateName(v, label, required) {
    if (v === '') return required ? `${label} is required.` : '';
    if (v.includes('  ')) return 'Double space is not allowed.';
    if (hasEdgeSpace(v)) return 'Spaces at the start or end are not allowed.';
    if (/\d/.test(v)) return `Numbers are not allowed in ${label}.`;
    if (/[^A-Za-z ]/.test(v)) return `Special characters are not allowed in ${label}.`;
    if (v.length < 2 || v.length > 21) return `${label} must be 2 to 21 characters.`;
    if (v === v.toUpperCase()) return 'Do not use all capital letters.';
    if (/(.)\1\1/i.test(v)) return 'Three consecutive identical letters are not allowed.';
    if (!isTitleCase(v)) {
        if (/(^| )[a-z]/.test(v)) return 'First letter of your name must be capital.';
        return 'Only the first letter of each word may be capital.';
    }
    return '';
}

function validateNameExtension(choice, other) {
    if (choice === '') return '';
    if (choice === 'Other') {
        if (other === '') return 'Please type your name extension.';
        if (!OTHER_EXTENSIONS.includes(other)) return 'Name extension must be I, II, III, IV, IX or X.';
        return '';
    }
    if (!EXTENSIONS.includes(choice)) return 'Please select a valid name extension.';
    return '';
}

function validateBirthdate(v) {
    if (v === '') return 'Birthdate is required.';
    const born = new Date(v + 'T00:00:00');
    if (isNaN(born)) return 'Please enter a valid birthdate.';
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (born > today) return 'Birthdate cannot be in the future.';
    let age = today.getFullYear() - born.getFullYear();
    const months = today.getMonth() - born.getMonth();
    if (months < 0 || (months === 0 && today.getDate() < born.getDate())) age--;
    if (age < 18) return 'You must be at least 18 years old to register.';
    return '';
}

function validatePurok(v) {
    if (v === '') return 'Purok / Street is required.';
    if (v.includes('  ')) return 'Double space is not allowed.';
    if (hasEdgeSpace(v)) return 'Spaces at the start or end are not allowed.';
    if (/[^A-Za-z0-9 ]/.test(v)) return 'Special characters are not allowed in Purok / Street.';
    if (v.length < 3 || v.length > 50) return 'Purok / Street must be 3 to 50 characters.';
    return '';
}

function validateTitleCaseText(v, label) {
    if (v === '') return `${label} is required.`;
    if (v.includes('  ')) return 'Double space is not allowed.';
    if (hasEdgeSpace(v)) return 'Spaces at the start or end are not allowed.';
    if (/\d/.test(v)) return `Numbers are not allowed in ${label}.`;
    if (/[^A-Za-z ]/.test(v)) return `Special characters are not allowed in ${label}.`;
    if (v.length < 2 || v.length > 50) return `${label} must be 2 to 50 characters.`;
    if (!isTitleCase(v)) {
        if (/(^| )[a-z]/.test(v)) return `First letter of each word in ${label} must be capital.`;
        return `Only the first letter of each word in ${label} may be capital.`;
    }
    return '';
}

function validateZip(v) {
    if (v === '') return 'ZIP Code is required.';
    if (!/^\d{4}$/.test(v)) return 'ZIP Code must be exactly 4 digits.';
    return '';
}

function validateChoice(v, allowed, label) {
    if (v === '') return `${label} is required.`;
    if (!allowed.includes(v)) return `Please select a valid ${label.toLowerCase()}.`;
    return '';
}

function validateContact(v) {
    if (v === '') return 'Contact Number is required.';
    if (!/^09\d{9}$/.test(v)) return 'Contact number must be 11 digits and start with 09.';
    return '';
}

function validateGuardian(v) {
    if (v === '') return '';
    return validateTitleCaseText(v, 'Guardian Name');
}

function validateAnswer(v) {
    if (v === '') return 'Answer is required.';
    if (v.includes('  ')) return 'Double space is not allowed.';
    if (hasEdgeSpace(v)) return 'Spaces at the start or end are not allowed.';
    if (/[^A-Za-z ]/.test(v)) return 'Answer may contain letters and spaces only.';
    if (v.length < 2 || v.length > 50) return 'Answer must be 2 to 50 characters.';
    return '';
}

function validateEmail(v) {
    if (v === '') return 'Email is required.';
    if (/\s/.test(v)) return 'Email must not contain spaces.';
    if (v.length > 100 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return 'Please enter a valid email address.';
    return '';
}

function validateUsername(v) {
    if (v === '') return 'Username is required.';
    if (/[^A-Za-z0-9]/.test(v)) return 'Username may contain letters and digits only.';
    if (!/^[A-Za-z]/.test(v)) return 'Username must start with a letter.';
    if (v.length < 8 || v.length > 17) return 'Username must be 8 to 17 characters.';
    return '';
}

function passwordStrength(v) {
    const kinds = [/[a-z]/, /[A-Z]/, /[0-9]/, /[^A-Za-z0-9]/].filter((r) => r.test(v)).length;
    if (kinds <= 1) return 'Weak';
    return kinds === 4 ? 'Strong' : 'Medium';
}

function validatePassword(v) {
    if (v === '') return 'Password is required.';
    if (v.includes(' ')) return 'Password must not contain spaces.';
    if (v.length < 9 || v.length > 64) return 'Password must be 9 to 64 characters.';
    if (passwordStrength(v) === 'Weak') return 'Password is too weak. Mix uppercase, lowercase, numbers or symbols.';
    return '';
}

function validatePassword2(p, p2) {
    if (p2 === '') return 'Please re-enter your password.';
    if (p !== p2) return 'Passwords do not match.';
    return '';
}

const val = (id) => document.getElementById(id).value;

// Usernames the server already told us about, so we do not ask twice
const takenNames = new Set();
const freeNames = new Set();
let checkTimer;

const rules = {
    id_number: () => validateIdNumber(val('id_number')),
    first_name: () => validateName(val('first_name'), 'First Name', true),
    middle_name: () => validateName(val('middle_name'), 'Middle Name', false),
    family_name: () => validateName(val('family_name'), 'Family Name', true),
    name_extension: () => validateNameExtension(val('name_extension'), val('name_extension_other')),
    birthdate: () => validateBirthdate(val('birthdate')),
    purok_street: () => validatePurok(val('purok_street')),
    barangay: () => validateTitleCaseText(val('barangay'), 'Barangay'),
    municipality: () => validateTitleCaseText(val('municipality'), 'Municipality / City'),
    province: () => validateTitleCaseText(val('province'), 'Province'),
    country: () => validateTitleCaseText(val('country'), 'Country'),
    zip_code: () => validateZip(val('zip_code')),
    course: () => validateChoice(val('course'), COURSES, 'Course'),
    year_level: () => validateChoice(val('year_level'), YEAR_LEVELS, 'Year level'),
    contact_number: () => validateContact(val('contact_number')),
    guardian_name: () => validateGuardian(val('guardian_name')),
    email: () => validateEmail(val('email')),
    username: () => validateUsername(val('username')) || (takenNames.has(val('username')) ? 'This username is already taken.' : ''),
    password: () => validatePassword(val('password')),
    password2: () => validatePassword2(val('password'), val('password2')),
    answer_2: () => validateAnswer(val('answer_2')),
    answer_5: () => validateAnswer(val('answer_5')),
    answer_7: () => validateAnswer(val('answer_7')),
    consent: () => (document.getElementById('consent').checked ? '' : 'You must agree to the Privacy Notice to register.'),
};

// A field stays quiet until the user types in it or leaves it
const touched = {};

// The green "available" text uses the same box as errors, but is never returned as an error
function show(id) {
    const message = rules[id]();
    const box = document.getElementById(id).closest('.field') || document.getElementById(id).closest('.consent-box');
    const target = box.querySelector('.error');
    const isFree = id === 'username' && message === '' && freeNames.has(val('username'));
    target.textContent = isFree ? 'Username is available.' : message;
    target.classList.toggle('ok', isFree);
    return message;
}

Object.keys(rules).forEach((id) => {
    const input = document.getElementById(id);
    const isChange = input.tagName === 'SELECT' || input.type === 'date' || input.type === 'checkbox';
    const liveEvent = isChange ? 'change' : 'input';

    input.addEventListener(liveEvent, () => {
        if (touched[id] || input.value !== '') {
            touched[id] = true;
            show(id);
        }
        if (id === 'password' && touched.password2) {
            show('password2');
        }
    });

    input.addEventListener('blur', () => {
        touched[id] = true;
        show(id);
    });
});

document.getElementById('name_extension_other').addEventListener('input', () => {
    touched.name_extension = true;
    show('name_extension');
});

async function checkUsername(name) {
    try {
        const response = await fetch('php/background/check_username.php?username=' + encodeURIComponent(name));
        const data = await response.json();
        // Ignore the answer if the user has already typed something else
        if (val('username') !== name) return;
        (data.taken ? takenNames : freeNames).add(name);
        show('username');
    } catch (e) {}
}

document.getElementById('username').addEventListener('input', () => {
    clearTimeout(checkTimer);
    const name = val('username');
    if (validateUsername(name) !== '' || takenNames.has(name) || freeNames.has(name)) return;
    checkTimer = setTimeout(() => checkUsername(name), 400);
});

document.querySelector('form').addEventListener('submit', (event) => {
    let firstBad = null;
    Object.keys(rules).forEach((id) => {
        touched[id] = true;
        if (show(id) && !firstBad) {
            firstBad = id;
        }
    });
    if (firstBad) {
        event.preventDefault();
        document.getElementById(firstBad).focus();
    }
});

const passwordBox = document.getElementById('password');
const meter = document.getElementById('password-meter');

function updateMeter() {
    const level = passwordBox.value === '' ? '' : passwordStrength(passwordBox.value);
    meter.className = 'meter ' + level.toLowerCase();
    meter.querySelector('.meter-text').textContent = level;
}

passwordBox.addEventListener('input', updateMeter);
updateMeter();