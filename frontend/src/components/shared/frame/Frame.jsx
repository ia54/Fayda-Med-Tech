import React from "react";
import Title from "../title/Title";

const Frame = ({ title }) => {
  return (
    <div className="pt-[72px] min-h-[400px] md:min-h-[700px] lg:min-h-[996px] w-full bg-[url('/images/banner/AboutBg.png')] bg-no-repeat bg-cover bg-center">
      <div className="sectionMargin pt-[160px] md:pt-[400px] lg:pt-[600px] mb-[80px] md:mb-[120px] lg:mb-[150px] ">
        <Title title={title} />
      </div>
    </div>
  );
};

export default Frame;
