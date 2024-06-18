function showError(error_id) {
    document.querySelector(`.error-block[data-id="${error_id}"] .error-details`).style.display = "block";
    document.querySelector(`.error-block[data-id="${error_id}"] .show-error`).style.display = "none";
    document.querySelector(`.error-block[data-id="${error_id}"] .hide-error`).style.display = "inline-block";
}

function hideError(error_id) {
    document.querySelector(`.error-block[data-id="${error_id}"] .error-details`).style.display = "none";
    document.querySelector(`.error-block[data-id="${error_id}"] .show-error`).style.display = "inline-block";
    document.querySelector(`.error-block[data-id="${error_id}"] .hide-error`).style.display = "none";
}