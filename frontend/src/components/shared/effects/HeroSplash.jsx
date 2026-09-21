import React from "react";

const HeroSplash = () => {
  return (
    <div
      aria-hidden
      className="pointer-events-none absolute inset-0 overflow-hidden z-0"
    >
      {/* Top-left blob */}
      <span
        className="hero-blob hero-blob--a"
        style={{
          top: "-80px",
          left: "-80px",
          width: "320px",
          height: "320px",
          background:
            "radial-gradient( circle at 30% 30%, rgba(12,147,170,0.45), rgba(12,94,170,0.15) 60%, transparent 70%)",
        }}
      />

      {/* Top-right blob */}
      <span
        className="hero-blob hero-blob--b"
        style={{
          top: "-60px",
          right: "-100px",
          width: "260px",
          height: "260px",
          background:
            "radial-gradient( circle at 70% 30%, rgba(112,167,206,0.45), rgba(12,147,170,0.18) 60%, transparent 70%)",
        }}
      />

      {/* Bottom-center blob */}
      <span
        className="hero-blob hero-blob--c"
        style={{
          bottom: "-120px",
          left: "50%",
          transform: "translateX(-50%)",
          width: "380px",
          height: "380px",
          background:
            "radial-gradient( circle at 50% 50%, rgba(16,62,70,0.35), rgba(12,147,170,0.18) 55%, transparent 70%)",
        }}
      />
    </div>
  );
};

export default HeroSplash;
