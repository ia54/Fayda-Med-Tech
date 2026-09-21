import React, { useEffect, useRef, useState } from "react";

const Reveal = ({
  as: Tag = "div",
  animation = "up", // 'up' | 'left' | 'right' | 'zoom'
  delay = 0,
  duration = 700,
  threshold = 0.2,
  once = true,
  className = "",
  children,
  ...rest
}) => {
  const ref = useRef(null);
  const [inView, setInView] = useState(false);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const obs = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setInView(true);
          if (once) obs.disconnect();
        } else if (!once) {
          setInView(false);
        }
      },
      { threshold }
    );
    obs.observe(el);
    return () => obs.disconnect();
  }, [threshold, once]);

  const classes = [
    "reveal",
    animation === "left" && "reveal--left",
    animation === "right" && "reveal--right",
    animation === "zoom" && "reveal--zoom",
    animation === "up" && "reveal--up",
    inView && "is-inview",
    className,
  ]
    .filter(Boolean)
    .join(" ");

  return (
    <Tag
      ref={ref}
      className={classes}
      style={{
        transitionDuration: `${duration}ms`,
        transitionDelay: `${delay}ms`,
      }}
      {...rest}
    >
      {children}
    </Tag>
  );
};

export default Reveal;
