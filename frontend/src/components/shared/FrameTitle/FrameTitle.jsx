// import React from "react";

// const FrameTitle = ({ title }) => {
//   return (
//     <div className="sectionMargin pt-[600px] mb-[150px] ">
//       <h2
//         className="font-semibold text-[97px] tracking-[0%] font-trap
//                bg-gradient-to-r from-[#0C93AA] to-[#0C5EAA]
//                bg-clip-text text-transparent flex justify-center items-center"
//       >
//         {title}
//       </h2>
//     </div>
//   );
// };

// export default FrameTitle;

const FrameTitle = ({ title }) => {
  return (
    <div className="relative pt-[72px] min-h-[360px] md:min-h-[600px] lg:min-h-[900px] w-full bg-[url('/images/banner/AboutBg.png')] bg-no-repeat bg-cover bg-center flex flex-col justify-center">
      <div className="sectionMargin">
        <h2 className="font-semibold text-3xl sm:text-5xl md:text-6xl lg:text-[80px] xl:text-[97px] tracking-[0%] font-trap bg-gradient-to-r from-[#0C93AA] to-[#0C5EAA] bg-clip-text text-transparent flex justify-center items-center text-center">
          {title}
        </h2>
      </div>
      <div className="absolute -top-20 bg-gradient-to-b from-transparent via-white to-transparent w-full inset-x-0 h-40"></div>
    </div>
  );
};

export default FrameTitle;
