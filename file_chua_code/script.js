const books = [
    {
        title: "Dế Mèn Phiêu Lưu Ký",
        author: "Tô Hoài",
        category: "Thiếu nhi",
        price: 55000,
        cover: "#bb805d",
        coverInk: "#fff3dd",
        coverTitle: "DẾ MÈN<br>PHIÊU LƯU KÝ",
        label: "TỦ SÁCH TUỔI THƠ",
    },
    {
        title: "Đắc Nhân Tâm",
        author: "Dale Carnegie",
        category: "Kỹ năng",
        price: 86000,
        cover: "#506a58",
        coverInk: "#f6f0dc",
        coverTitle: "ĐẮC<br>NHÂN TÂM",
        label: "KỸ NĂNG SỐNG",
    },
    {
        title: "Nhà Giả Kim",
        author: "Paulo Coelho",
        category: "Văn học",
        price: 79000,
        cover: "#c5a363",
        coverInk: "#fff9e9",
        coverTitle: "NHÀ<br>GIẢ KIM",
        label: "PAULO COELHO",
    },
    {
        title: "Tuổi Trẻ Đáng Giá Bao Nhiêu",
        author: "Rosie Nguyễn",
        category: "Kỹ năng",
        price: 72000,
        cover: "#82969a",
        coverInk: "#f8f6ec",
        coverTitle: "TUỔI TRẺ<br>ĐÁNG GIÁ<br>BAO NHIÊU",
        label: "ROSIE NGUYỄN",
    },
];

const bookGrid = document.querySelector("#book-grid");
const searchInput = document.querySelector("#book-search");
const emptyMessage = document.querySelector("#empty-message");
const cartCount = document.querySelector(".cart-count");
const cartButton = document.querySelector(".cart-button");
const toast = document.querySelector("#toast");
const menuToggle = document.querySelector(".menu-toggle");
const mainNav = document.querySelector("#main-nav");

let activeCategory = "Tất cả";
let itemCount = 0;
let toastTimer;

function formatPrice(price) {
    return new Intl.NumberFormat("vi-VN").format(price) + "đ";
}

function renderBooks() {
    const searchTerm = searchInput.value.trim().toLocaleLowerCase("vi");
    const filteredBooks = books.filter((book) => {
        const matchesCategory = activeCategory === "Tất cả" || book.category === activeCategory;
        const searchText = `${book.title} ${book.author} ${book.category}`.toLocaleLowerCase("vi");
        return matchesCategory && searchText.includes(searchTerm);
    });

    bookGrid.replaceChildren();
    emptyMessage.hidden = filteredBooks.length > 0;

    filteredBooks.forEach((book) => {
        const card = document.createElement("article");
        card.className = "book-card";

        const cover = document.createElement("div");
        cover.className = "book-cover";
        cover.style.setProperty("--cover", book.cover);
        cover.style.setProperty("--cover-ink", book.coverInk);
        cover.setAttribute("aria-label", `Bìa sách ${book.title}`);

        const coverInner = document.createElement("div");
        coverInner.className = "cover-inner";

        const coverTitle = document.createElement("strong");
        coverTitle.innerHTML = book.coverTitle;

        const coverLabel = document.createElement("small");
        coverLabel.textContent = book.label;
        coverInner.append(coverTitle, coverLabel);
        cover.append(coverInner);

        const category = document.createElement("span");
        category.className = "book-category";
        category.textContent = book.category.toLocaleUpperCase("vi");

        const title = document.createElement("h3");
        title.textContent = book.title;

        const author = document.createElement("p");
        author.className = "book-author";
        author.textContent = book.author;

        const bottom = document.createElement("div");
        bottom.className = "book-card-bottom";

        const price = document.createElement("span");
        price.className = "book-price";
        price.textContent = formatPrice(book.price);

        const addButton = document.createElement("button");
        addButton.className = "add-button";
        addButton.type = "button";
        addButton.textContent = "Thêm vào giỏ";
        addButton.setAttribute("aria-label", `Thêm ${book.title} vào giỏ hàng`);
        addButton.addEventListener("click", () => addToCart(book.title));

        bottom.append(price, addButton);
        card.append(cover, category, title, author, bottom);
        bookGrid.append(card);
    });
}

function showToast(message) {
    toast.textContent = message;
    toast.classList.add("is-visible");
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
        toast.classList.remove("is-visible");
    }, 2400);
}

function addToCart(bookTitle) {
    itemCount += 1;
    cartCount.textContent = itemCount;
    cartButton.setAttribute("aria-label", `Giỏ hàng, ${itemCount} sản phẩm`);
    showToast(`Đã thêm “${bookTitle}” vào giỏ hàng.`);
}

document.querySelectorAll(".filter-button").forEach((button) => {
    button.addEventListener("click", () => {
        activeCategory = button.dataset.category;
        document.querySelector(".filter-button.is-active")?.classList.remove("is-active");
        button.classList.add("is-active");
        renderBooks();
    });
});

searchInput.addEventListener("input", renderBooks);

menuToggle.addEventListener("click", () => {
    const isExpanded = menuToggle.getAttribute("aria-expanded") === "true";
    menuToggle.setAttribute("aria-expanded", String(!isExpanded));
    mainNav.classList.toggle("is-open", !isExpanded);
});

mainNav.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
        menuToggle.setAttribute("aria-expanded", "false");
        mainNav.classList.remove("is-open");
    });
});

cartButton.addEventListener("click", () => {
    showToast(itemCount > 0 ? `Giỏ hàng hiện có ${itemCount} sản phẩm.` : "Giỏ hàng của bạn đang trống.");
});

renderBooks();
