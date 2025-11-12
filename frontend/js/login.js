/**
 * JavaScript para Login
 */

console.log("[Login] Cargando script de login...");

// Obtener base URL desde window.BASE_URL (inyectado por index.php) o usar default
const BASE_URL = (typeof window !== "undefined" && window.BASE_URL) || "";
const API_BASE = `${BASE_URL}/api/v1`;

console.log("[Login] BASE_URL:", BASE_URL);
console.log("[Login] API_BASE:", API_BASE);

document.addEventListener("DOMContentLoaded", () => {
  console.log("[Login] DOM cargado, configurando event listeners...");
  const form = document.getElementById("login-form");
  if (form) {
    form.addEventListener("submit", handleLogin);
    console.log("[Login] Event listener de submit configurado");
  } else {
    console.error("[Login] No se encontró el formulario de login");
  }
});

async function handleLogin(e) {
  e.preventDefault();

  console.log("[Login] handleLogin llamado");

  const email = document.getElementById("email").value;
  const password = document.getElementById("password").value;

  console.log("[Login] Email:", email);
  console.log("[Login] Intentando login en:", `${API_BASE}/auth/login`);

  const container = document.getElementById("alert-container");
  container.innerHTML = "";

  try {
    // Esperar a que el API client esté disponible
    if (typeof API === "undefined") {
      console.warn("[Login] API client no disponible, usando fetch directo como fallback");
      // Fallback a fetch directo si API client no está disponible
      const response = await fetch(`${API_BASE}/auth/login`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ email, password }),
      });
      const result = await response.json();
      handleLoginResult(result);
      return;
    }

    // Usar API client
    const result = await API.post("/auth/login", { email, password });
    console.log("[Login] Resultado parseado:", result);
    
    handleLoginResult(result);
  } catch (error) {
    console.error("Error:", error);
    showError("Error de conexión. Verifica que el servidor esté ejecutándose.");
  }
}

/**
 * Maneja el resultado del login (extraído para reutilización)
 */
function handleLoginResult(result) {
  if (result && result.success && result.data) {
    // Guardar tokens
    console.log("[Login] ========================================");
    console.log("[Login] Guardando tokens en localStorage...");
    console.log("[Login] Access token (primeros 20 chars):", result.data.access_token?.substring(0, 20) + "...");
    console.log("[Login] Refresh token (primeros 20 chars):", result.data.refresh_token?.substring(0, 20) + "...");
    console.log("[Login] Tenant ID:", result.data.tenant_id);
    console.log("[Login] User ID:", result.data.user?.id);
    console.log("[Login] Role:", result.data.role);
    
    localStorage.setItem("access_token", result.data.access_token);
    localStorage.setItem("refresh_token", result.data.refresh_token);
    localStorage.setItem("tenant_id", result.data.tenant_id);
    localStorage.setItem("user", JSON.stringify(result.data.user));
    localStorage.setItem("user_role", result.data.role || "");

    console.log("[Login] Tokens guardados correctamente");
    console.log("[Login] Verificando que se guardaron correctamente...");
    console.log("[Login] access_token en localStorage:", !!localStorage.getItem("access_token"));
    console.log("[Login] refresh_token en localStorage:", !!localStorage.getItem("refresh_token"));
    console.log("[Login] tenant_id en localStorage:", localStorage.getItem("tenant_id"));
    console.log(
      "[Login] Access token expira en:",
      result.data.expires_in || "N/A",
      "segundos"
    );

    // El auto-refresh se inicializará automáticamente cuando se cargue el API client en el dashboard
    // No intentar inicializarlo aquí porque el API client puede no estar cargado aún

    // Redirigir según rol
    const role = result.data.role;
    const redirectUrl = role === "owner_admin" 
      ? `${BASE_URL}/owner/dashboard`
      : `${BASE_URL}/dashboard`;
    
    console.log("[Login] Redirigiendo a:", redirectUrl);
    console.log("[Login] ========================================");
    
    // Pequeño delay para asegurar que localStorage se guarde
    setTimeout(() => {
      window.location.href = redirectUrl;
    }, 100);
  } else {
    console.error("[Login] Login falló:", result);
    showError(result?.message || "Error al iniciar sesión");
  }
}

function showError(message) {
  const container = document.getElementById("alert-container");
  container.innerHTML = `<div class="alert alert-danger">${message}</div>`;
}
