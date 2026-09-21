import { useEffect, useRef, useState } from "react";

export default function FadeInOnScroll({
  children,
  className = "",
  delay = 0,
  isFirstElement = false,
}) {
  const ref = useRef(null);
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    const node = ref.current;
    if (!node) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setTimeout(() => setIsVisible(true), delay);
          observer.disconnect();
        }
      },
      { threshold: 0.15 }
    );

    observer.observe(node);

    return () => observer.disconnect();
  }, [delay]);

  return (
    <div
      ref={ref}
      className={`
        transition-all duration-1000 ease-out
        ${
          isVisible
            ? "opacity-100 translate-y-0"
            : isFirstElement
            ? "opacity-0"
            : "opacity-0 translate-y-20"
        }
        ${className}
      `}
    >
      {children}
    </div>
  );
}
