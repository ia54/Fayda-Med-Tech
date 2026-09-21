import { useEffect, useState } from "react";
import { Link } from "react-router";

import { Menu, X } from "lucide-react"; // hamburger & close icon
import CustomButton from "../../../ui/CustomButton";

const Navbar = () => {
  const [isOpen, setIsOpen] = useState(false);
  const [isScrolled, setIsScrolled] = useState(false);

  useEffect(() => {
    const handleScroll = () => {
      if (window.scrollY > 10) {
        setIsScrolled(true);
      } else {
        setIsScrolled(false);
      }
    };

    window.addEventListener("scroll", handleScroll);
    return () => window.removeEventListener("scroll", handleScroll);
  }, []);

  return (
    <div
      className={`sectionGap py-4 md:py-5 fixed top-0 inset-x-0 max-md:px-4 w-full z-50 transition-all duration-300 ${
        isScrolled
          ? "bg-white/5 backdrop-blur-md shadow-xl shadow-black/5"
          : "bg-transparent"
      }`}
    >
      <div className="flex justify-between items-center">
        {/* Logo */}
        <Link
          to="/"
          className={`w-40 sm:w-[233px] sm:h-[52px] cursor-pointer ${
            isOpen ? "z-50" : ""
          }`}
        >
          <img
            src="/images/logo/logo.png"
            className="w-full h-full sm:object-cover"
            alt="Logo"
          />
        </Link>

        {/* Desktop nav */}
        <ul className="hidden md:flex gap-x-4 backdrop-blur-md lg:gap-x-[32px] text-[#1E1E1E] items-center rounded-full bg-[#BABABA3a] px-[16px] lg:px-[24px] py-[8px] max-w-lg transition-all duration-300">
          {navlinks.map((link) => (
            <Link key={link.name} to={link.path}>
              <li className="hover:bg-primary transition-all duration-300 hover:px-[24px] hover:py-[8px] hover:rounded-[80px] hover:text-[#FFFFFF]">
                {link.name}
              </li>
            </Link>
          ))}
        </ul>

        {/* Desktop button */}
        <div className="hidden md:block">
          <CustomButton variant="primary">Request a demo</CustomButton>
        </div>

        {/* Mobile Hamburger */}
        <div className="md:hidden relative z-50">
          <button
            onClick={() => setIsOpen(!isOpen)}
            className="text-[#1E1E1E] rounded-full cursor-pointer bg-[#BABABA4D] px-2 py-2"
          >
            {isOpen ? <X size={28} /> : <Menu size={28} />}
          </button>
        </div>
      </div>

      {/* Mobile menu */}
      <div
        className={`fixed z-40 menuText top-0 left-0 w-full h-screen bg-gradient-to-b from-[#FFFFFF] to-[#0C93AA] py-4 transition-all duration-500 ease-in-out ${
          isOpen
            ? "opacity-100 translate-y-0"
            : "opacity-0 -translate-y-4 pointer-events-none"
        }`}
      >
        <div
          className="md:hidden bg-gradient-to-r from-[#0C93AA] to-[#0C5EAA] 
               bg-clip-text text-transparent pt-[70px]"
        >
          <ul className="flex flex-col gap-y-6 text-center mt-20">
            {navlinks.map((link) => (
              <Link
                key={link.name}
                to={link.path}
                onClick={() => setIsOpen(false)}
              >
                <li className="hover:text-primary text-xl">{link.name}</li>
              </Link>
            ))}
          </ul>

          <div className="w-full flex justify-center mt-10">
            <CustomButton className="py-3" variant="primary">
              Request a demo
            </CustomButton>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Navbar;

const navlinks = [
  { name: "Home", path: "/" },
  { name: "Solutions", path: "/solutions" },
  { name: "About", path: "/about" },
  { name: "Pricing", path: "/pricing" },
  { name: "Contact", path: "/contact" },
];
