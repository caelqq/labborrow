const birthdate = document.getElementById('birthdate');
const age = document.getElementById('age');
const extension = document.getElementById('name_extension');
const extensionOther = document.getElementById('name_extension_other');

function updateAge() {
    const born = new Date(birthdate.value);
    if (isNaN(born)) {
        age.value = '';
        return;
    }
    const today = new Date();
    let years = today.getFullYear() - born.getFullYear();
    const monthDiff = today.getMonth() - born.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < born.getDate())) {
        years--;
    }
    age.value = years >= 0 ? years : '';
}

function toggleOther() {
    const isOther = extension.value === 'Other';
    extensionOther.hidden = !isOther;
    if (!isOther) {
        extensionOther.value = '';
    }
}

birthdate.addEventListener('change', updateAge);
extension.addEventListener('change', toggleOther);
updateAge();
toggleOther();