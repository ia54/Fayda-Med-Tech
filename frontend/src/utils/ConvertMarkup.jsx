import React from "react";
import { sanitizeRichText } from "./sanitizeRichText.mjs";

const ConvertMarkup = ({ children }) => (
  <div dangerouslySetInnerHTML={{ __html: sanitizeRichText(children) }} />
);

export default ConvertMarkup;
