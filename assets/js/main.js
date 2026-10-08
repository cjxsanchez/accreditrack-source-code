// Global Search Functionality
document.addEventListener("DOMContentLoaded", () => {
  const globalSearch = document.getElementById("globalSearch")
  const notificationBell = document.getElementById("notificationBell")
  const notificationDropdown = document.getElementById("notificationDropdown")

  if (globalSearch) {
    globalSearch.addEventListener("input", debounce(performGlobalSearch, 500))
  }

  // Notification Bell Click
  if (notificationBell) {
    notificationBell.addEventListener("click", (e) => {
      e.stopPropagation()
      notificationDropdown.classList.toggle("show")
      if (notificationDropdown.classList.contains("show")) {
        loadNotifications()
      }
    })
  }

  // Close notifications when clicking outside
  document.addEventListener("click", (e) => {
    if (notificationDropdown && !notificationDropdown.contains(e.target)) {
      notificationDropdown.classList.remove("show")
    }
  })

  // Close modal on background click
  document.querySelectorAll(".modal").forEach((modal) => {
    modal.addEventListener("click", (e) => {
      if (e.target === modal) {
        modal.classList.remove("show")
      }
    })
  })
})

// Debounce function
function debounce(func, wait) {
  let timeout
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout)
      func(...args)
    }
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}

// Global Search
function performGlobalSearch(e) {
  const searchTerm = e.target.value.trim()

  if (searchTerm.length < 2) {
    return
  }

  // Get current page to determine what to search
  const currentPage = window.location.pathname.split("/").pop()

  fetch(`../api/search.php?q=${encodeURIComponent(searchTerm)}&page=${currentPage}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        displaySearchResults(data.results)
      }
    })
    .catch((error) => console.error("Search error:", error))
}

function displaySearchResults(results) {
  // Filter table rows based on search results
  const currentPage = window.location.pathname.split("/").pop()

  if (currentPage === "user_management.php") {
    filterTableRows("usersTable", results)
  } else if (currentPage === "programs.php") {
    filterTableRows("programsTable", results)
  } else if (currentPage === "validation.php") {
    filterTableRows("documentsTable", results)
  } else if (currentPage === "archives.php") {
    filterTableRows("archivedTable", results)
  }
}

function filterTableRows(tableId, searchResults) {
  const table = document.getElementById(tableId)
  if (!table) return

  const rows = table.getElementsByTagName("tr")

  for (let i = 1; i < rows.length; i++) {
    const row = rows[i]
    const text = row.textContent.toLowerCase()
    const searchTerm = document.getElementById("globalSearch").value.toLowerCase()

    if (text.includes(searchTerm)) {
      row.style.display = ""
    } else {
      row.style.display = "none"
    }
  }
}

// Load Notifications
function loadNotifications() {
  fetch("../api/notifications.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        displayNotifications(data.notifications)
      }
    })
    .catch((error) => console.error("Notification error:", error))
}

function displayNotifications(notifications) {
  const container = document.getElementById("notificationsList")

  if (notifications.length === 0) {
    container.innerHTML = '<div class="text-center p-4 text-muted">No notifications</div>'
    return
  }

  container.innerHTML = notifications
    .map(
      (notif) => `
    <div class="notification-item ${notif.is_read == 0 ? "unread" : ""}" onclick="markNotificationRead(${notif.id})">
      <div class="notification-item-header">
        <div class="notification-item-title">${escapeHtml(notif.title)}</div>
        <div class="notification-item-time">${formatTimeAgo(notif.created_at)}</div>
      </div>
      <div class="notification-item-message">${escapeHtml(notif.message)}</div>
    </div>
  `,
    )
    .join("")
}

function formatTimeAgo(dateString) {
  const date = new Date(dateString)
  const now = new Date()
  const seconds = Math.floor((now - date) / 1000)

  if (seconds < 60) return "Just now"
  if (seconds < 3600) return Math.floor(seconds / 60) + "m ago"
  if (seconds < 86400) return Math.floor(seconds / 3600) + "h ago"
  return Math.floor(seconds / 86400) + "d ago"
}

function markNotificationRead(notificationId) {
  fetch("../api/notifications.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "mark_single_read", id: notificationId }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        loadNotifications()
        updateNotificationBadge()
      }
    })
}

function markAllRead() {
  fetch("../api/notifications.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "mark_read" }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        loadNotifications()
        updateNotificationBadge()
      }
    })
}

function updateNotificationBadge() {
  fetch("../api/notifications.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const unread = data.notifications.filter((n) => n.is_read == 0).length
        const badge = document.querySelector(".notification-badge")
        if (unread > 0) {
          if (badge) {
            badge.textContent = unread
          } else {
            const bell = document.getElementById("notificationBell")
            bell.innerHTML += `<span class="notification-badge">${unread}</span>`
          }
        } else {
          if (badge) badge.remove()
        }
      }
    })
}

// Modal Functions
function openModal(modalId) {
  const modal = document.getElementById(modalId)
  if (modal) {
    modal.classList.add("show")
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId)
  if (modal) {
    modal.classList.remove("show")
  }
}

// Show Alert
function showAlert(message, type = "info") {
  const alertDiv = document.createElement("div")
  alertDiv.className = `alert alert-${type}`
  alertDiv.innerHTML = `
    <i class="fas fa-${type === "success" ? "check-circle" : type === "danger" ? "exclamation-triangle" : "info-circle"}"></i>
    ${message}
  `

  const contentArea = document.querySelector(".content-area")
  if (contentArea) {
    contentArea.insertBefore(alertDiv, contentArea.firstChild)
    setTimeout(() => alertDiv.remove(), 5000)
  }
}

// Filter Table
function filterTable(input, tableId) {
  const filter = input.value.toUpperCase()
  const table = document.getElementById(tableId)
  const tr = table.getElementsByTagName("tr")

  for (let i = 1; i < tr.length; i++) {
    const txtValue = tr[i].textContent || tr[i].innerText
    if (txtValue.toUpperCase().indexOf(filter) > -1) {
      tr[i].style.display = ""
    } else {
      tr[i].style.display = "none"
    }
  }
}

// Escape HTML
function escapeHtml(text) {
  const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  }
  return text.replace(/[&<>"']/g, (m) => map[m])
}

// Toggle Sidebar (for mobile)
function toggleSidebar() {
  const sidebar = document.querySelector(".sidebar")
  if (sidebar) {
    sidebar.classList.toggle("active")
  }
}

// Confirm Delete
function confirmDelete(message = "Are you sure you want to delete this?") {
  return confirm(message)
}

// Format File Size
function formatFileSize(bytes) {
  if (bytes === 0) return "0 Bytes"
  const k = 1024
  const sizes = ["Bytes", "KB", "MB", "GB"]
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i]
}
