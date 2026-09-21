import ConvertMarkup from "../../../utils/ConvertMarkup";
import HeroSplash from "../effects/HeroSplash";
import Reveal from "../effects/Reveal";

const Banner = ({
  subtitle,
  Fayda,
  med,
  title,
  description,
  buttonText,
  bannerImage,
}) => {
  return (
    <div
      className="pt-[72px] min-h-[60vh] md:min-h-[80vh] lg:min-h-[996px] w-full bg-cover bg-center bg-no-repeat"
      style={{ backgroundImage: `url(${bannerImage})` }}
    >
      <HeroSplash />
      <div className="flex flex-col lg:flex-row justify-between items-center gap-8 sectionGap pt-[120px] md:pt-[160px] lg:pt-[200px]">
        <div>
          {/* <p */}
          <Reveal
            as="p"
            animation="up"
            className="text-lg lg:text-[24px] bg-gradient-to-r from-[#1DC6E3] to-[#103E46] 
               bg-clip-text text-transparent tracking-[-4%] font-medium font-trap"
          >
            {subtitle}
          </Reveal>
          {/* </p> */}
          {/* <h1 */}
          <Reveal
            as="h1"
            animation="up"
            delay={80}
            className="mt-[10px] max-w-[745px] text-[56px] sm:text-[72px] md:text-[120px] lg:text-[180px] xl:text-[243.53px] font-semibold tracking-[-4%] font-trap
      bg-gradient-to-r from-[#0C93AA] to-[#70A7CE9C] 
               bg-clip-text text-transparent leading-[100%]"
          >
            {Fayda}
            {/* </h1> */}
          </Reveal>
          {/* <h4 */}
          <Reveal
            as="h4"
            animation="up"
            delay={140}
            className="ml-0 lg:ml-[78px] text-[40px] sm:text-[64px] md:text-[96px] lg:text-[136px] font-bold tracking-[-4%] font-trap
      bg-gradient-to-r from-[#0C93AA] to-[#70A7CE9C] flex lg:justify-end
               bg-clip-text text-transparent leading-[100%]"
          >
            {med}
            {/* </h4> */}
          </Reveal>

          {/* <h4 */}
          <Reveal
            as="p"
            animation="up"
            delay={200}
            className="mt-[10px] flex lg:justify-end text-[40px] sm:text-[64px] md:text-[96px] lg:text-[130px] font-bold tracking-[-4%] font-trap
      bg-gradient-to-r from-[#0C93AA] to-[#D7E3A0] 
               bg-clip-text text-transparent leading-[100%]"
          >
            {title}
            {/* </h4> */}
          </Reveal>
        </div>

        <div className="w-full max-w-md p-[20px]   rounded-[10px] bg-[#6FB6C242]">
          <p className=" text-base md:text-[20px] text-[#557277] font-normal leading-[150%] tracking-[0%] font-lufga">
            {description}
          </p>
          {/* <button  */}
          <Reveal
            as="button"
            animation="up"
            delay={260}
            className="mt-[24px] bg-[#0C93AA] text-[#FFFFFF] px-[28px] py-[16px] rounded-[28px] text-[16px] md:px-[42px] md:py-[18px] md:rounded-[36px] md:text-[18px]"
          >
            {buttonText}
            {/* </button> */}
          </Reveal>
        </div>
      </div>
    </div>
  );
};

export default Banner;
