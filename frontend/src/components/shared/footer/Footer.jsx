import { Link } from "react-router";
import cms from "../../../constants/cms";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import SharedSkeleton from "../SharedSkeleton";

const links = [
  {
    title: "Company",
    links: [
      { name: "Pricing", to: "/pricing" },
      { name: "About", to: "/about" },
      { name: "Solutions", to: "/solutions" },
      { name: "Contact", to: "/contact" },
    ],
  },
  {
    title: "Product",
    links: [
      { name: "Analysis", to: "/analysis" },
      { name: "Scale", to: "/scale" },
      { name: "Developer", to: "/developer" },
    ],
  },

  { title: "API", links: [{ name: "Pricing", to: "/pricing" }] },
];

const Footer = () => {
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;

  if (isLoadingCMS) {
    return <SharedSkeleton />;
  }

  return (
    <footer className="bg-white sectionGap mt-[80px] mb-[50px]">
      <div className="flex flex-col lg:flex-row justify-between items-start gap-8">
        {/* Newsletter */}
        <div>
          <h3 className="footerHeading mb-2">
            {cmsData?.[cms.common.newsLetterCta.commonNewsLetterCtaTitle] ||
              "Subscribe to Newsletter"}
          </h3>
          <p className=" mb-[30px]">
            {cmsData?.[
              cms.common.newsLetterCta.commonNewsLetterCtaDescription
            ] ||
              "Get Monthly insights from founders around the globe.No spam - promise. "}
            <br />
          </p>
          <form className="flex flex-col sm:flex-row sm:items-center bg-[rgba(240,240,240,0.4)] border border-[#EDEDED] gap-3 sm:gap-[50px] rounded-full sm:rounded-[100px] overflow-hidden">
            <input
              type="email"
              placeholder={
                cmsData?.[
                  cms.common.newsLetterCta.commonNewsLetterCtaInputPlaceholder
                ] || "Enter your email"
              }
              className="flex-1 placeholder:text-[15px] leading-[100%] px-[16px] sm:pl-[30px] outline-none max-sm:py-3 placeholder:text-[#BABABA]"
            />
            <button
              type="submit"
              className="bg-[#0C93AA] rounded-[16px] sm:rounded-[24px] sm:mr-[8px] sm:my-[8px] text-white px-[20px] py-[12px] sm:px-[26px] sm:py-[14px] text-[15px] font-medium font-lufga hover:bg-[#0b909f]"
            >
              {cmsData?.[
                cms.common.newsLetterCta.commonNewsLetterCtaButtonText
              ] || "Subscribe"}
            </button>
          </form>
        </div>

        {links.map((section) => (
          <div key={section.title}>
            <h4 className="text-2xl font-trap-light mb-3">{section.title}</h4>
            <ul className="space-y-4">
              {section.links.map((link) => (
                <li
                  key={link.name}
                  className="text-black/60 hover:text-black hover:underline transition-all duration-300 hover:scale-95"
                >
                  <Link to={link.to}>{link.name}</Link>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>

      {/* Bottom */}
      <div className="mt-10  pt-6 flex flex-col lg:flex-row lg:items-center justify-between  ">
        {/* Logo */}
        <div className="flex flex-col lg:flex-row items-start lg:items-center gap-y-[20px] gap-x-[8px] mb-8 lg:mb-0">
          <div className="w-[200px] md:w-[233px] h-[52px]">
            <img src="/images/logo/logo.png" className="w-full h-full" />
          </div>
          <span className="text-[16px] font-lufga font-normal text-[#8A8A8A]">
            @{new Date().getFullYear()}{" "}
            {cmsData?.[cms.common.footer.footerCopyrightText] ||
              "Faydamed. All rights reserved."}
          </span>
        </div>

        {/* Links */}
        <div className="flex gap-x-[30px]">
          <Link
            to="/privacy-policy"
            className="text-[16px] leading-[50px] font-lufga font-normal text-[#8A8A8A]"
          >
            Privacy Policy
          </Link>
          <span>•</span>
          <Link
            to="/terms"
            className="text-[16px] leading-[50px] font-lufga font-normal text-[#8A8A8A]"
          >
            Terms & Conditions
          </Link>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
