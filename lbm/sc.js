function showForm(formId) {
    document.querySelectorAll(".form-box").forEach(form=> form.classList.remove("active"));
    document.getElementById(formId).classList.add("active");
}

function filterTable() {
  const input = document.getElementById("searchInput");
  const filter = input.value.toLowerCase();
  const table = document.getElementById("booksTable");
  const tr = table.getElementsByTagName("tr");

  for (let i = 1; i < tr.length; i++) {
    let row = tr[i];
    let text = row.textContent.toLowerCase();
    row.style.display = text.includes(filter) ? "" : "none";
  }
}