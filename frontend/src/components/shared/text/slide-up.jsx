import { motion, useInView } from "framer-motion";
import { useRef } from "react";

export const TextRevealTypewriter = ({
  children,
  className = "",
  delay = 0,
  speed = 0.05,
}) => {
  const ref = useRef(null);
  const isInView = useInView(ref, { once: true, margin: "-100px" });

  // Convert children to array and process
  const childrenArray = Array.isArray(children) ? children : [children];

  // Count total characters first (excluding br tags)
  let totalChars = 0;
  childrenArray.forEach((child) => {
    if (typeof child === "string") {
      totalChars += child.length;
    }
  });

  let currentCharIndex = 0;

  return (
    <div ref={ref} className={className}>
      {childrenArray.map((child, segmentIndex) => {
        // If it's a break element, render it directly
        if (child?.type === "br") {
          return <br key={segmentIndex} />;
        }

        // If it's not a string, skip it
        if (typeof child !== "string") {
          return null;
        }

        // Process text segment
        const letters = child.split("");
        return letters.map((letter, letterIndex) => {
          const globalIndex = currentCharIndex;
          currentCharIndex++;

          return (
            <motion.span
              key={`${segmentIndex}-${letterIndex}`}
              initial={{ opacity: 0 }}
              animate={isInView ? { opacity: 1 } : { opacity: 0 }}
              transition={{
                duration: 0.1,
                delay: delay + globalIndex * speed,
                ease: "easeIn",
              }}
              className="inline-block"
            >
              {letter === " " ? "\u00A0" : letter}
            </motion.span>
          );
        });
      })}
    </div>
  );
};
