import cms from "../../../constants/cms";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import SharedSkeleton from "../../shared/SharedSkeleton";

const AboutContact = () => {
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });

  if (isLoadingCMS) {
    return <SharedSkeleton />;
  }
  return (
    <div className="relative pt-[700px] w-full bg-[url('/images/banner/AboutBg.png')] bg-no-repeat bg-cover bg-center flex flex-col justify-center">
      <div className="sectionMargin">
        <h2 className="font-semibold text-3xl sm:text-5xl md:text-6xl lg:text-[80px] xl:text-[97px] tracking-[0%] font-trap bg-gradient-to-r from-[#0C93AA] to-[#0C5EAA] bg-clip-text text-transparent flex justify-center items-center text-center">
          Contact Us
        </h2>
      </div>

      {/* <Title title="Contact Us" /> */}
      <div
        className="mt-[100px] max-lg:py-20 min-h-[480px] md:min-h-[560px] lg:min-h-[1000px] w-full flex flex-col justify-center sectionGap  bg-center bg-no-repeat bg-cover"
        style={{
          background: `url(${cmsData?.[cms.common.cta.commonCtaBg] || "/images/about/removal.jpg"})`,
        }}
      >
        <div className="rounded-[20px] shadow-[0px_4px_30px_rgba(0,0,0,0.15)] bg-white/10 backdrop-blur-md md:max-w-2xl mx-auto">
          <form className="bg-white/80 md:bg-white/60 backdrop-blur-md rounded-[20px] px-[24px] sm:px-[40px] md:px-[60px] py-[28px] sm:py-[36px] md:py-[48px] w-full">
            {/* First + Last Name */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
              <div>
                <label className="block text-[#174B4F] font-semibold mb-2">
                  First Name
                </label>
                <input
                  type="text"
                  className="w-full border-b border-gray-300 focus:outline-none focus:border-[#1DC6E3] bg-transparent"
                />
              </div>
              <div>
                <label className="block text-[#174B4F] font-semibold mb-2">
                  Last Name
                </label>
                <input
                  type="text"
                  className="w-full border-b border-gray-300 focus:outline-none focus:border-[#1DC6E3] bg-transparent"
                />
              </div>
            </div>

            {/* Email */}
            <div className="mb-6">
              <label className="block text-[#174B4F] font-semibold mb-2">
                Email
              </label>
              <input
                type="email"
                className="w-full border-b border-gray-300 focus:outline-none focus:border-[#1DC6E3] bg-transparent"
              />
            </div>

            {/* Message */}
            <div className="mb-6">
              <label className="block text-[#174B4F] font-semibold mb-2">
                Message
              </label>
              <textarea
                placeholder="Write your message.."
                rows="4"
                className="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:border-[#1DC6E3] bg-transparent placeholder-gray-400"
              ></textarea>
            </div>

            {/* Button */}
            <div className="text-center">
              <button
                type="submit"
                className="bg-[#1DC6E3] text-white px-8 py-3 rounded-full font-medium hover:bg-[#17a7c2] transition"
              >
                Send Message
              </button>
            </div>
          </form>
        </div>
      </div>
      <div className="absolute -top-20 bg-gradient-to-b from-transparent via-white to-transparent w-full inset-x-0 h-40"></div>
    </div>
  );
};

export default AboutContact;
