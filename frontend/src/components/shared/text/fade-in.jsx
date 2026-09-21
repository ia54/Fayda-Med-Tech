// import { motion, useTransform } from "framer-motion";
// import { Fragment } from "react";

// const AnimatedText = ({
//   children,
//   scrollProgress,
//   startProgress = 0,
//   endProgress = 1,
//   className = "",
// }) => {
//   // Convert children to array and process
//   const childrenArray = Array.isArray(children) ? children : [children];

//   // Count total letters first
//   let totalLetters = 0;
//   childrenArray.forEach((child) => {
//     if (typeof child === "string") {
//       totalLetters += child.length;
//     }
//   });

//   let currentLetterIndex = 0;

//   return (
//     <span className={`${className}`}>
//       {childrenArray.map((child, segmentIndex) => {
//         // If it's a break element, render it directly
//         if (child?.type === "br" || child === "\n") {
//           return <br key={segmentIndex} />;
//         }

//         // If it's not a string, skip it
//         if (typeof child !== "string") {
//           return null;
//         }

//         // Process text segment
//         const letters = child.split("");
//         return (
//           <Fragment key={segmentIndex}>
//             {letters.map((letter, letterIndex) => {
//               const globalIndex = currentLetterIndex;
//               currentLetterIndex++;

//               // Calculate when this letter should start and finish animating
//               const letterStart =
//                 startProgress +
//                 (globalIndex / totalLetters) * (endProgress - startProgress);
//               const letterEnd =
//                 startProgress +
//                 ((globalIndex + 1) / totalLetters) *
//                   (endProgress - startProgress);

//               // eslint-disable-next-line react-hooks/rules-of-hooks
//               const letterColor = useTransform(
//                 scrollProgress,
//                 [letterStart, letterEnd],
//                 ["#CCCCCC", "#103E46"]
//               );

//               return (
//                 <motion.span
//                   key={`${segmentIndex}-${letterIndex}`}
//                   style={{ color: letterColor }}
//                   className="transition-colors duration-300"
//                 >
//                   {letter === " " ? "\u00A0" : letter}
//                 </motion.span>
//               );
//             })}
//           </Fragment>
//         );
//       })}
//     </span>
//   );
// };

// export const AnimatedParagraph = ({
//   children,
//   scrollProgress,
//   startProgress = 0,
//   endProgress = 1,
//   className = "",
// }) => {
//   return (
//     <p className={className}>
//       <AnimatedText
//         scrollProgress={scrollProgress}
//         startProgress={startProgress}
//         endProgress={endProgress}
//       >
//         {children}
//       </AnimatedText>
//     </p>
//   );
// };

import { motion, useTransform } from "framer-motion";

const AnimatedText = ({
  children,
  scrollProgress,
  startProgress = 0,
  endProgress = 1,
  className = "",
}) => {
  const childrenArray = Array.isArray(children) ? children : [children];
  let totalLetters = 0;

  // 1. Process text into segments (words and spaces/breaks)
  const textSegments = [];
  childrenArray.forEach((child) => {
    if (typeof child === "string") {
      // Use regex to split by spaces and keep the spaces in the array
      const parts = child.match(/(\s+|\S+)/g) || [];
      textSegments.push(...parts);
      // Count letters in non-space parts for totalLetters calculation
      parts.forEach((part) => {
        if (/\S+/.test(part)) {
          // Check if it's a non-space word
          totalLetters += part.length;
        }
      });
    } else if (child?.type === "br" || child === "\n") {
      textSegments.push(child);
    }
    // Skip other non-string elements (like nulls, or other React components)
  });

  let currentLetterIndex = 0;

  return (
    <span className={`${className}`}>
      {textSegments.map((segment, segmentIndex) => {
        // If it's a break element, render it directly
        if (segment?.type === "br" || segment === "\n") {
          return <br key={segmentIndex} />;
        }

        // If it's a space, render it as a simple span or use non-breaking space
        if (/\s+/.test(segment)) {
          // Using a span here instead of just the space character
          // to ensure it takes up space, but you could also just return segment
          return <span key={segmentIndex}>{segment}</span>;
        }

        // Process a word (non-space string segment)
        const letters = segment.split("");

        // Wrap the word in a span with inline-block for proper word wrapping
        return (
          <span key={segmentIndex} style={{ display: "inline-block" }}>
            {letters.map((letter, letterIndex) => {
              const globalIndex = currentLetterIndex;
              currentLetterIndex++;

              // Calculate when this letter should start and finish animating
              const letterStart =
                startProgress +
                (globalIndex / totalLetters) * (endProgress - startProgress);
              const letterEnd =
                startProgress +
                ((globalIndex + 1) / totalLetters) *
                  (endProgress - startProgress);

              // eslint-disable-next-line react-hooks/rules-of-hooks
              const letterColor = useTransform(
                scrollProgress,
                [letterStart, letterEnd],
                ["#CCCCCC", "#103E46"]
              );

              return (
                <motion.span
                  key={`${segmentIndex}-${letterIndex}`}
                  style={{ color: letterColor }}
                  className="transition-colors duration-300"
                >
                  {letter}
                </motion.span>
              );
            })}
          </span>
        );
      })}
    </span>
  );
};

export const AnimatedParagraph = ({
  children,
  scrollProgress,
  startProgress = 0,
  endProgress = 1,
  className = "",
}) => {
  return (
    <p className={className}>
      <AnimatedText
        scrollProgress={scrollProgress}
        startProgress={startProgress}
        endProgress={endProgress}
      >
        {children}
      </AnimatedText>
    </p>
  );
};
