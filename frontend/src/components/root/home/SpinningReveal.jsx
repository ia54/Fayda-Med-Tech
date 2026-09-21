import { useEffect, useMemo, useRef, useState } from "react";
import { TextRevealTypewriter } from "../../shared/text/slide-up";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import cms from "../../../constants/cms";
import { HeroSkeleton } from "../../shared/SharedSkeleton";
import ConvertMarkup from "../../../utils/ConvertMarkup";

export default function SpinningReveal() {
  const containerRef = useRef(null);
  const [width, setWidth] = useState(1038);

  useEffect(() => {
    const el = containerRef.current;
    if (!el) return;
    const update = () => {
      const rect = el.getBoundingClientRect();
      setWidth(rect.width || 1038);
    };
    update();
    window.addEventListener("resize", update);
    return () => window.removeEventListener("resize", update);
  }, []);

  const BASE_W = 1038;
  const BASE_H = 1035;
  const scale = Math.max(0.4, Math.min(1.0, width / BASE_W));

  const ITEM_COUNT = 28;
  const RADIUS = 420 * scale;
  const DURATION = 16;

  const PREFIX = "We make";
  const SUFFIX = "BILLING YOU EASIER";

  // Build 28 items
  const ringItems = useMemo(
    () =>
      Array.from({ length: ITEM_COUNT }, (_, i) => {
        const angle = (i / ITEM_COUNT) * 360;
        const theta = (angle * Math.PI) / 180;

        const x = Math.cos(theta) * RADIUS;
        const y = Math.sin(theta) * RADIUS;

        const size = (86 + Math.sin(theta * 2) * 10) * scale;
        const rotate = angle - 90;

        return (
          <div
            key={i}
            className="absolute rounded-[25px]"
            style={{
              width: `${size}px`,
              height: `${size * 0.974}px`,
              left: `calc(50% + ${x}px)`,
              top: `calc(50% + ${y}px)`,
              transform: `translate(-50%, -50%) rotate(${rotate}deg)`,
              background:
                "linear-gradient(219.82deg, rgba(12,147,170,0.1653) 0.54%, rgba(70,137,148,0.29) 102.41%)",
              boxShadow: "inset 0 0 7.8px 2.54px #F3FFF0",
              backdropFilter: "blur(2.4px)",
            }}
          />
        );
      }),
    [ITEM_COUNT, RADIUS, scale],
  );

  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  // console.log({ cmsData, isLoadingCMS });

  const rawTitle =
    cmsData?.[cms.homepage.circleTypeWriting.homeCircleTypeWritingTitle] || "";

  const lines = rawTitle.split(" ");
  // ["We","Make","BILLING","YOU","EASIER"]

  const finalLines = [
    `${lines[0]} ${lines[1]}`, // We Make
    `${lines[2]} ${lines[3]}`, // BILLING YOU
    lines[4], // EASIER
  ];

  if (isLoadingCMS) {
    return <HeroSkeleton />;
  }

  return (
    <div
      ref={containerRef}
      className="relative mx-auto bg-white w-full max-w-[1038px] h-[480px] sm:h-[640px] md:h-[820px] lg:h-[1035px] overflow-hidden"
    >
      {/* Rotating ring */}
      <div
        className="absolute inset-0"
        style={{
          animation: `spin ${DURATION}s linear infinite`,
        }}
      >
        <div
          className="absolute left-1/2 top-1/2"
          style={{
            width: BASE_W * scale,
            height: BASE_H * scale,
            transform: "translate(-50%, -50%) rotate(73.86deg)",
          }}
        >
          <div
            className="absolute left-1/2 top-1/2"
            style={{ transform: "translate(-50%, -50%)" }}
          >
            {ringItems}
          </div>
        </div>
      </div>

      {/* Center text */}
      <div
        className="absolute text-center pointer-events-none"
        style={{
          left: "50%",
          top: "50%",
          transform: "translate(-50%, -50%)",
          width: Math.min(width * 0.92, 980),
          zIndex: 5,
        }}
      >
        <div className="max-w-2xl mx-auto space-y-2">
          {finalLines.map((line, idx) => (
            <TextRevealTypewriter
              key={idx}
              className="block bg-clip-text font-trap font-semibold text-transparent bg-gradient-to-r from-[#0C93AA] to-[#0C5EAA] text-4xl md:text-6xl lg:text-8xl"
              delay={idx * 0.6}
            >
              {line}
            </TextRevealTypewriter>
          ))}
        </div>
      </div>

      <style>{`
        @keyframes spin { 
          to { transform: rotate(360deg); } 
        }
        @keyframes typing {
          from { width: 0ch; }
          to { width: ${SUFFIX.length}ch; }
        }
      `}</style>
    </div>
  );
}
