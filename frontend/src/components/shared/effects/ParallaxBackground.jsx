import React, { useEffect, useRef, useState } from "react";

const ParallaxBackground = ({
  image,
  speed = -0.2,
  className = "",
  children,
  style = {},
}) => {
  const ref = useRef(null);
  const [offset, setOffset] = useState(0);

  useEffect(() => {
    const onScroll = () => {
      // Use global scroll offset for simplicity; works well for tall headers
      setOffset(window.scrollY || window.pageYOffset || 0);
    };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  return (
    <div ref={ref} className={`relative overflow-hidden ${className}`} style={style}>
      <img
        aria-hidden
        alt=""
        src={image}
        className="absolute inset-0 w-full h-full object-cover z-0 select-none pointer-events-none will-change-transform"
        style={{ transform: `translateY(${offset * speed}px)` }}
      />
      <div className="relative z-10">{children}</div>
    </div>
  );
};

export default ParallaxBackground;
