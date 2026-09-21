import React from "react";

const ConvertMarkup = ({ children }) => {
  return <div dangerouslySetInnerHTML={{ __html: children }}></div>;
};

export default ConvertMarkup;
