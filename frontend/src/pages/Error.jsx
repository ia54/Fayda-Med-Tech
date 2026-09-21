import React from "react";
import { FaExclamationTriangle } from "react-icons/fa";

const Error = () => {
  return (
    <div className="flex flex-col items-center justify-center h-screen text-center px-4">
      <FaExclamationTriangle className="text-red-500 text-6xl mb-4" />
      <h1 className="text-3xl font-bold mb-2">Oops! Something went wrong.</h1>
      <p className="text-gray-600 mb-6">
        We couldn’t find the page you were looking for.
      </p>
      <a
        href="/"
        className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition"
      >
        Go Back Home
      </a>
    </div>
  );
};

export default Error;
