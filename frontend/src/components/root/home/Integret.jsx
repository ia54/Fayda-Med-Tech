import cms from "../../../constants/cms";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import FadeInOnScroll from "../../shared/effects/in-view";
import { Skeleton } from "../../shared/SharedSkeleton";

const Integret = () => {
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;

  if (isLoadingCMS) {
    return <Skeleton />;
  }

  return (
    <div className="flex flex-col lg:flex-row justify-between items-center sectionGap mt-[80px] gap-8">
      <FadeInOnScroll>
        <h2 className="bg-clip-text font-trap max-w-lg font-semibold text-transparent bg-gradient-to-r from-[#0C93AA] to-[#0C5EAA] text-3xl md:text-4xl lg:text-5xl">
          {cmsData?.[cms.homepage.appIntegration.homeAppIntegrationLeftTitle] ||
            "Easy to Integrate with your usual 3rd party apps"}
        </h2>
      </FadeInOnScroll>

      <div className="w-full max-w-[650px] h-auto">
        <img
          src={
            cmsData?.[
              cms.homepage.appIntegration.homeAppIntegrationRightImage
            ] || "/images/home/integret.png"
          }
          alt=""
          className="w-full h-auto"
        />
      </div>
    </div>
  );
};

export default Integret;
