import React from "react";
import Reveal from "../effects/Reveal";

const Title = ({ title }) => {
  return (
    <div className="sectionMargin my-[30px]">
      <Reveal
        as="h2"
        animation="up"
        className="font-semibold  text-2xl sm:text-4xl md:text-5xl lg:text-[72px] xl:text-[97px] tracking-[0%] font-trap 
               bg-gradient-to-r from-[#0C93AA] to-[#0C5EAA] 
               bg-clip-text text-transparent flex justify-center items-center text-center"
      >
        {title}
      </Reveal>
    </div>
  );
};

export default Title;
