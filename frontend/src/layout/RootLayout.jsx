import Footer from "../components/shared/footer/Footer";
import Navbar from "../components/shared/nav/Navbar";
import { Outlet, useLocation } from "react-router";
import React, { useEffect } from "react";

const ScrollToTop = () => {
  const location = useLocation();
  useEffect(() => {
    if (typeof window !== "undefined") {
      window.scrollTo({ top: 0, left: 0, behavior: "auto" });
    }
  }, [location.pathname]);
  return null;
};

const RootLayout = () => {
  return (
    <div>
      <ScrollToTop />
      <Navbar />
      <Outlet />
      <Footer />
    </div>
  );
};

export default RootLayout;
