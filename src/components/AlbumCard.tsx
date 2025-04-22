
import { Skeleton } from "@/components/ui/skeleton";

type AlbumCardProps = {
  id: number;
  title: string;
  photoCount: number;
  coverImage: string;
  date: string;
  isLoading?: boolean;
};

const AlbumCardSkeleton = () => (
  <div className="border rounded-md p-4">
    <Skeleton className="h-[200px] w-full mb-3" />
    <Skeleton className="h-6 w-3/4 mb-2" />
    <Skeleton className="h-5 w-1/2" />
  </div>
);

export const AlbumCard = ({ title, photoCount, coverImage, date, isLoading }: AlbumCardProps) => {
  if (isLoading) {
    return <AlbumCardSkeleton />;
  }
  
  return (
    <div className="border rounded-md overflow-hidden">
      <div className="h-[200px] bg-gray-100 relative">
        <img 
          src={coverImage} 
          alt={title} 
          className="w-full h-full object-cover"
        />
      </div>
      <div className="p-4">
        <h3 className="text-lg font-semibold">{title}</h3>
        <div className="text-sm text-gray-600 flex justify-between mt-1">
          <span>{photoCount} photos</span>
          <span>{date}</span>
        </div>
      </div>
    </div>
  );
};
