// Public recovery requests stay out of persisted auth state and query caches.
export async function postPasswordRecovery(path: "forgot-password" | "reset-password", body: Record<string, string>) {
  const base = (process.env.NEXT_PUBLIC_API_BASE_URL || "http://localhost:8000/api").replace(/\/$/, "")
  let response: Response
  try {
    response = await fetch(`${base}/${path}`, {
      method: "POST", credentials: "omit", cache: "no-store", referrerPolicy: "no-referrer",
      headers: { "Content-Type": "application/json", Accept: "application/json" }, body: JSON.stringify(body),
    })
  } catch { throw new Error("Could not connect. Check your connection and try again.") }
  if (response.status === 429) throw new Error("Too many attempts. Please wait a minute before trying again.")
  if (!response.ok) {
    if (path === "reset-password" && [400, 422].includes(response.status)) throw new Error("The link is invalid or expired, or the passwords do not meet the requirements. Request a new link if needed.")
    throw new Error("The request could not be completed. Please try again or contact your administrator.")
  }
}
