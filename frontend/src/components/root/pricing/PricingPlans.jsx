import { useState } from "react";
import { PiFireFill } from "react-icons/pi";
import FadeInOnScroll from "../../shared/effects/in-view";

const PricingPlans = () => {
  const [isYearly, setIsYearly] = useState(false);
  return (
    <div className="sectionGap mt-[80px] bg-white">
      {/* Header */}
      <div className="text-center mb-14">
        <p className=" mb-[20px] text-center uppercase  leading-[20px] tracking-[1.3px] text-[#ABABAB] text-sm  font-lufga font-normal ">
          Pricing
        </p>
        <h2 className="pricingAmount mx-auto">Plan Tiers</h2>
        <p className="text-[#7C7C7C] max-w-[374px] mx-auto text-center leading-[150%] tracking-[-0.3px] font-normal font-lufga mb-[40px]">
          Flexible plans and solutions for business of all sizes
        </p>

        {/* Toggle */}
        {/* <div className="flex items-center justify-center  pb-[64px] space-x-3">
          <span 
          className="text-gray-500"
          >Monthly</span>
          <div className="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" className="sr-only" />
            <div className="w-14 h-7 bg-gray-200 rounded-full"></div>
            <div className="absolute left-1 top-1 w-5 h-5 bg-white rounded-full shadow-md transition"></div>
          </div>
          <span className="text-gray-900 font-medium">
            Yearly <span className="text-blue-500">Save 20%</span>
          </span>
        </div> */}

        <div className="flex items-center justify-center pb-[64px] space-x-3">
          <span
            className={`text-base font-lufga  leading-[-0.2px]${
              !isYearly ? "text-[#000000] font-bold" : "text-[#7C7C7C]"
            }`}
          >
            Monthly
          </span>
          <div
            onClick={() => setIsYearly(!isYearly)}
            className="relative inline-flex items-center cursor-pointer"
          >
            <input
              type="checkbox"
              checked={isYearly}
              readOnly
              className="sr-only"
            />
            <div
              className={`w-14 h-7 rounded-full transition ${
                isYearly ? "bg-[#0C93AA]" : "bg-gray-200"
              }`}
            ></div>
            <div
              className={`absolute top-1 w-5 h-5 bg-white rounded-full shadow-md transition ${
                isYearly ? "translate-x-7 left-0" : "left-1"
              }`}
            ></div>
          </div>
          <span
            className={`text-sm font-lufga  leading-[-0.2px]${
              isYearly ? "text-[#000000] font-bold" : "text-[#7C7C7C]"
            }`}
          >
            Yearly{" "}
            <span className="text-[#222222] font-normal font-lufga bg-[#EAEAEA] rounded-full px-[8px] py-[2px]">
              Save 20%
            </span>
          </span>
        </div>
      </div>

      {/* Pricing Cards */}
      <div className="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-[12px]">
        {/* Starter */}
        <FadeInOnScroll className=" rounded-[20px] bg-[#F5F5F554]  px-[20px] py-[24px]   flex flex-col">
          <img
            src="/images/pricing/star.png"
            alt=""
            className="h-[40px] w-[40px] object-cover mx-auto"
          />
          <h4 className="pricingName">Starter</h4>
          <p className="pricingAmount">
            {" "}
            {isYearly ? "$2,870/year per provider" : "$299/month per provider"}
          </p>
          <p className="pricingPara">
            For small clinics and pharmacies handling PI cases occasionally
          </p>

          <ul className=" pricingLi ">
            <li>Claim dashboard (manual entry + uploads)</li>
            <hr className=" text-[#D2D2D2] my-[16px] max-w-[356px]" />
            <li>AI form parsing (HCFA1500/PIP)</li>
            <hr className=" text-[#D2D2D2] my-[16px] max-w-[356px]" />
            <li>Basic lien & AOB e-signatures</li>
            <hr className=" text-[#D2D2D2] my-[16px] max-w-[356px]" />
            <li>Arabic & Spanish SMS templates</li>
            <hr className=" text-[#D2D2D2] my-[16px] max-w-[356px]" />
            <li>Up to 200 claims/month</li>
            <hr className=" text-[#D2D2D2] mt-[16px] max-w-[356px]" />
          </ul>

          <button className="mt-[60px] bg-[#0C93AA] text-white rounded-full py-[10px]  font-medium  transition w-full mx-auto">
            Get started
          </button>
        </FadeInOnScroll>

        {/* Professional (highlighted) */}
        <FadeInOnScroll className="mt-[50px] lg:mt-0  rounded-t-none rounded-b-[20px] bg-[#F5F5F554]  px-[20px] py-[24px] flex flex-col relative">
          <div className="absolute -top-10 left-[50%] -translate-x-1/2 bg-[#0C93AA] text-white text-sm font-lufga font-normal leading-[20px] py-[12px]  tracking-[-0.2px]  rounded-t-full w-full text-center">
            <div className="flex items-center justify-center  gap-x-[4px]">
              {" "}
              <PiFireFill className="text-white" /> Most popular
            </div>
          </div>
          <img
            src="/images/pricing/star.png"
            alt=""
            className="h-[40px] w-[40px] object-cover mx-auto"
          />
          <h4 className="pricingName">Professional</h4>
          <p className="pricingAmount">
            {isYearly ? "$6,710/year per provider" : "$699/month per provider"}
          </p>
          <p className="pricingPara">
            For high-volume PI providers & mid-size firms needing automation.
          </p>

          <ul className="pricingLi">
            <li>Everything in Starter, plus:</li>
            <hr className=" text-[#D2D2D2] my-[16px] max-w-[356px]" />
            <li>Advanced denial prediction + AI appeal drafts</li>
            <hr className=" text-[#D2D2D2] my-[16px] max-w-[356px]" />
            <li>Attorney portal (read-only access, no PHI)</li>
            <hr className=" text-[#D2D2D2] my-[16px] max-w-[356px]" />
            <li>Priority support & onboarding</li>
            <hr className=" text-[#D2D2D2] my-[16px] max-w-[356px]" />
            <li>Up to 1,000 claims/month</li>
            <hr className=" text-[#D2D2D2] mt-[16px] max-w-[356px]" />
          </ul>

          <button className="mt-[60px] bg-[#0C93AA] text-white rounded-full py-[10px]  font-medium  transition w-full mx-auto">
            Get started
          </button>
        </FadeInOnScroll>

        {/* Enterprise */}
        <FadeInOnScroll className=" rounded-[20px] bg-[#F5F5F554]  px-[20px] py-[24px]   flex flex-col">
          <img
            src="/images/pricing/star.png"
            alt=""
            className="h-[40px] w-[40px] object-cover mx-auto"
          />
          <h4 className="pricingName">Enterprise</h4>
          <p className="pricingAmount">Custom Pricing</p>
          <p className="pricingPara">
            For networks, law-med partnerships, or firms handling 2,000+
            claims/month.
          </p>

          <ul className="pricingLi">
            <li>All Professional features</li>
            <hr className=" text-[#D2D2D2] my-[16px] max-w-[356px]" />
            <li>White-labeled patient communications</li>
            <hr className=" text-[#D2D2D2] my-[16px] max-w-[356px]" />
            <li>API integration with Filevine / CASEpeer CRMs</li>
            <hr className=" text-[#D2D2D2] my-[16px] max-w-[356px]" />
            <li>SOC-2 compliance reports</li>
            <hr className=" text-[#D2D2D2] my-[16px] max-w-[356px]" />
            <li>Dedicated success manager</li>
            <hr className=" text-[#D2D2D2] my-[16px] max-w-[356px]" />
            <li>Unlimited claims</li>
            <hr className=" text-[#D2D2D2] mt-[16px] max-w-[356px]" />
          </ul>

          <button className="mt-[50px] bg-[#0C93AA] text-white rounded-full py-[10px]  font-medium   w-full mx-auto">
            Get started
          </button>
        </FadeInOnScroll>
      </div>
    </div>
  );
};

export default PricingPlans;
