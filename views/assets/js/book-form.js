document.querySelectorAll(".category-search").forEach((search) => {
    const list = search.nextElementSibling;
    const options = list.querySelectorAll(".category-option");
    const emptyMessage = list.querySelector(".category-empty");
    search.addEventListener("input", () => {
        const query = search.value.trim().toLocaleLowerCase("vi");
        let visibleCount = 0;
        options.forEach((option) => {
            const matches = option.textContent.toLocaleLowerCase("vi").includes(query);
            option.hidden = !matches;
            visibleCount += matches ? 1 : 0;
        });
        emptyMessage.hidden = visibleCount > 0;
    });
});

const newCategoryToggle = document.getElementById("toggle-new-category");
const newCategoryPanel = document.getElementById("new-category-panel");
newCategoryToggle.addEventListener("click", () => {
    const isHidden = newCategoryPanel.hidden;
    newCategoryPanel.hidden = !isHidden;
    newCategoryToggle.setAttribute("aria-expanded", String(isHidden));
    if (isHidden) {
        document.getElementById("new-categories").focus();
    }
});
