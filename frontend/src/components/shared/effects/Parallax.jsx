import React, { useEffect, useState } from "react";

const Parallax = ({ speed = -0.2, className = "", style = {}, children }) => {
  const [offset, setOffset] = useState(0);

  useEffect(() => {
    const onScroll = () => setOffset(window.scrollY || window.pageYOffset || 0);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  const transform = `translateY(${offset * speed}px)`;

  return (
    <div className={className} style={{ ...style, transform, willChange: "transform" }}>
      {children}
    </div>
  );
};

export default Parallax;

