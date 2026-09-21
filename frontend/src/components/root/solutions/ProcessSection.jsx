import {
  FileSignature,
  FileText,
  Globe,
  GraduationCap,
  Percent,
  Rocket,
  ShoppingBag,
  Video,
  Wallet,
} from "lucide-react";
import Reveal from "../../shared/effects/Reveal";

const steps = [
  { id: 1, label: "Smarter Claim Processing", icon: FileText },
  { id: 2, label: "Signing a Lease", icon: FileSignature },
  { id: 3, label: "Launching a Website", icon: Globe },
  { id: 4, label: "Training a Budtender", icon: GraduationCap },
  { id: 5, label: "Preparing for Launch", icon: Rocket },
  { id: 6, label: "Choosing Vendors", icon: ShoppingBag },
  { id: 7, label: "Running Promotions", icon: Percent },
  { id: 8, label: "Paying Vendors Without W-9s", icon: Wallet },
  { id: 9, label: "Sharing Surveillance Footage", icon: Video },
];

const details = {
  title1: "The Challenge",
  desc1:
    "Traditional PI billing requires staff to manually fill out CMS-1500 and PIP forms, retype data from scanned documents, and correct errors after insurers reject claims. This leads to wasted hours and a 25–40% denial rate.",
  title2: "Our Solution",
  points: [
    "AI-Powered OCR & NLP: Automatically extracts data from uploaded forms or PDFs.",
    "Real-Time Error Checks: Flags missing codes, absent signatures, or incomplete lien info before submission.",
    "One-Click Submission: Send electronically or generate ready-to-mail packets instantly.",
  ],
};

const ProcessSection = () => {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 gap-10 sectionGap mt-[100px]">
      {/* Left Steps */}
      <Reveal className="space-y-4" animation="left">
        {steps.map((step) => {
          const Icon = step.icon;
          return (
            <button
              key={step.id}
              className={`flex group items-center w-full text-left px-4 py-3 border-[#0CA6B7] text-gray-800 border rounded-xl transition hover:bg-[#E6F7F9] hover:border-[#0CA6B7] hover:text-[#0CA6B7] font-medium 
                `}
            >
              <span
                className={`flex bg-[#E6F7F9] text-[#0CA6B7] group-hover:bg-[#0CA6B7] group-hover:text-white items-center justify-center w-10 h-10 mr-3 rounded-lg border transition-colors duration-300`}
              >
                <Icon size={20} />
              </span>
              {step.label}
            </button>
          );
        })}
      </Reveal>

      {/* Right Details */}
      <Reveal
        className="bg-white border border-[#0CA6B7] h-fit rounded-2xl px-[20px] md:px-[34px] py-[28px] md:py-[50px] shadow-sm"
        animation="right"
      >
        <div className="mb-[18px] border border-[#EAEAEA] py-[15px] pl-[16px] md:pl-[22px] pr-[20px] md:pr-[44px] rounded-[13.5px]">
          <h3 className="text-xl md:text-2xl font-medium font-lufga leading-[120%] tracking-[0%] text-[#103E46] mb-[10px] md:mb-[13px]">
            {details.title1}
          </h3>
          <p className="max-w-[560px] text-base md:text-lg leading-[150%] tracking-[0%] font-normal font-lufga text-[#0C93AA] ">
            {details.desc1}
          </p>
        </div>

        <div className=" border border-[#EAEAEA] py-[15px] pl-[16px] md:pl-[22px] pr-[20px] md:pr-[44px] rounded-[13.5px]">
          <h3 className="text-xl md:text-2xl font-medium font-lufga leading-[120%] tracking-[0%] text-[#103E46] mb-[10px] md:mb-[13px]">
            {details.title2}
          </h3>
          <ul className="list-disc pl-5 space-y-4 max-w-[560px] text-base md:text-lg leading-[150%] tracking-[0%] font-normal font-lufga text-[#0C93AA]">
            {details.points.map((point, i) => (
              <li key={i}>{point}</li>
            ))}
          </ul>
        </div>
      </Reveal>
    </div>
  );
};

export default ProcessSection;
