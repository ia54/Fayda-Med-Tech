/**
 * Removes keys with null, undefined, or empty string values from an object.
 * @param {Object} obj - The object to clean
 * @returns {Object} A new object with only meaningful key-value pairs
 */
export const cleanObject = (obj) => {
  const cleaned = {};

  Object.entries(obj).forEach(([key, value]) => {
    if (
      value !== null &&
      value !== undefined &&
      !(typeof value === "string" && value.trim() === "")
    ) {
      cleaned[key] = value;
    }
  });

  return cleaned;
};
