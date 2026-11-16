document.addEventListener("submit", (event) => {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm
        && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});

document.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }
    const target = event.target.closest("[data-confirm]");
    if (target && !(target instanceof HTMLFormElement)
        && !window.confirm(target.dataset.confirm)) {
        event.preventDefault();
    }
});

document.addEventListener("error", (event) => {
    const image = event.target;
    if (image instanceof HTMLImageElement && image.hasAttribute("data-hide-on-error")) {
        image.hidden = true;
    }
}, true);
